<?php
namespace Tests\Feature;

use App\Domain\Runtime\RuntimeActionService;
use App\Domain\Runtime\BrainRuntimeActionAdapter;
use App\Enums\{AivvaControlMode,RuntimeActionStatus,RuntimeActionType};
use App\Models\{AivvaRuntimeLocation,Location,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuntimeActionLifecycleTest extends TestCase
{
    use RefreshDatabase;
    private RuntimeActionService $service;
    protected function setUp():void{parent::setUp();$this->seedCivilization();config()->set('aivva.runtime.enabled',true);config()->set('aivva.runtime.ai_control_enabled',true);config()->set('aivva.runtime.action_ttl_seconds',120);config()->set('aivva.runtime.action_lease_seconds',30);$this->service=app(RuntimeActionService::class);foreach([['test_location_001',0,0],['test_location_002',500,0],['test_social_area',250,250]]as[$id,$x,$y])AivvaRuntimeLocation::query()->updateOrCreate(['id'=>$id],compact('x','y')+['z'=>0,'label'=>$id,'enabled'=>true]);}
    public function test_move_to_lifecycle_and_duplicate_ack_are_idempotent():void{$a=$this->aiAivva();$r=$this->service->create($a,RuntimeActionType::MoveTo,['locationId'=>'test_location_001']);$this->assertSame(RuntimeActionStatus::Requested,$r->status);$e=$this->service->claim($r,'exec-1','client-1');$this->assertSame(RuntimeActionStatus::Executing,$e->status);$this->assertSame($e->id,$this->service->claim($r,'exec-1','client-1')->id);$c=$this->service->acknowledge($r,'exec-1',RuntimeActionStatus::Completed,['state'=>'IDLE']);$this->assertSame(RuntimeActionStatus::Completed,$c->status);$this->assertSame($c->version,$this->service->acknowledge($r,'exec-1',RuntimeActionStatus::Completed,['state'=>'IDLE'])->version);}
    public function test_illegal_transition_and_wrong_execution_lease_fail():void{$r=$this->service->create($this->aiAivva(),RuntimeActionType::Idle,[]);$this->expectExceptionMessage('Illegal runtime action transition.');$this->service->acknowledge($r,'missing',RuntimeActionStatus::Completed);}
    public function test_wrong_execution_id_cannot_complete_claimed_action():void{$r=$this->service->create($this->aiAivva(),RuntimeActionType::Idle,[]);$this->service->claim($r,'exec-good','client');$this->expectExceptionMessage('Execution lease mismatch.');$this->service->acknowledge($r,'exec-bad',RuntimeActionStatus::Completed);}
    public function test_expired_action_cannot_be_claimed():void{$r=$this->service->create($this->aiAivva(),RuntimeActionType::Idle,[]);$r->expires_at=now()->subSecond();$r->save();$this->expectExceptionMessage('Action expired.');$this->service->claim($r,'exec','client');}
    public function test_expired_lease_recovers_without_duplicate_action():void{$a=$this->aiAivva();$r=$this->service->create($a,RuntimeActionType::Idle,[]);$this->service->claim($r,'old-exec','old-client');$r->refresh()->update(['lease_expires_at'=>now()->subSecond()]);$active=$this->service->active($a);$this->assertSame($r->id,$active?->id);$this->assertSame(RuntimeActionStatus::Requested,$active?->status);$this->assertNull($active?->execution_id);}
    public function test_cancel_is_scoped_to_one_action_and_idempotent():void{$a=$this->aiAivva();$one=$this->service->create($a,RuntimeActionType::Idle,[]);$two=$this->service->create($a,RuntimeActionType::Say,['text'=>'AI generated hello']);$this->assertSame(RuntimeActionStatus::Cancelled,$this->service->cancel($one)->status);$this->assertSame(RuntimeActionStatus::Cancelled,$this->service->cancel($one)->status);$this->assertSame(RuntimeActionStatus::Requested,$two->fresh()->status);}
    public function test_payload_and_control_mode_validation():void{$human=$this->makeLivingAivva(User::factory()->create());$this->expectExceptionMessage('AI Twin does not control this AIVVA.');$this->service->create($human,RuntimeActionType::MoveTo,['locationId'=>'test_location_001']);}
    public function test_invalid_location_is_rejected():void{$this->expectExceptionMessage('Invalid runtime location.');$this->service->create($this->aiAivva(),RuntimeActionType::MoveTo,['locationId'=>'untrusted']);}
    public function test_source_action_is_idempotent():void{$a=$this->aiAivva();$source=$a->actions()->create(['type'=>'REST','payload'=>[],'status'=>'PENDING','initiated_by'=>'AI','idempotency_key'=>'source-1']);$one=$this->service->create($a,RuntimeActionType::Idle,[],'AI',$source->id);$two=$this->service->create($a,RuntimeActionType::Idle,[],'AI',$source->id);$this->assertSame($one->id,$two->id);}
    public function test_runtime_completion_synchronizes_the_source_brain_action():void{$a=$this->aiAivva();$source=$a->actions()->create(['type'=>'REST','payload'=>[],'status'=>'PENDING','initiated_by'=>'AI','idempotency_key'=>'source-sync']);$runtime=$this->service->create($a,RuntimeActionType::Idle,[],'AI',$source->id);$this->service->claim($runtime,'exec-sync','client-sync');$this->assertSame('RUNNING',$source->fresh()->status->value);$this->service->acknowledge($runtime,'exec-sync',RuntimeActionStatus::Completed,['worldState'=>'IDLE']);$source->refresh();$this->assertSame('COMPLETED',$source->status->value);$this->assertSame($runtime->id,$source->result['runtime_action_id']);$this->assertNull($a->fresh()->current_action_id);}
    public function test_brain_travel_action_maps_only_to_a_trusted_runtime_location():void{$a=$this->aiAivva();$logical=Location::query()->firstOrFail();$runtimeLocation=AivvaRuntimeLocation::query()->findOrFail('test_location_001');$runtimeLocation->logical_location_id=$logical->id;$runtimeLocation->save();$source=$a->actions()->create(['type'=>'TRAVEL','payload'=>['location_id'=>$logical->id],'status'=>'PENDING','initiated_by'=>'AI','idempotency_key'=>'brain-travel']);$runtime=app(BrainRuntimeActionAdapter::class)->dispatch($a,$source);$this->assertNotNull($runtime);$this->assertSame(RuntimeActionType::MoveTo,$runtime->type);$this->assertSame('test_location_001',$runtime->payload['locationId']);$this->assertSame($source->id,$runtime->source_action_id);}
    public function test_plaza_demo_brain_continues_with_four_distinct_actions():void
    {
        config()->set('aivva.runtime.plaza_demo_enabled',true);
        $luna=$this->aiAivva();$luna->name='LUNA';$luna->save();
        $move=$this->service->startPlazaDemo($luna);
        $this->assertSame(RuntimeActionType::MoveTo,$move->type);
        $actions=[$move];
        foreach ([RuntimeActionType::FaceTarget,RuntimeActionType::Interact,RuntimeActionType::Say] as $index=>$expected) {
            $current=$actions[$index];$execution='demo-exec-'.$index;
            $this->service->claim($current,$execution,'browser-demo');
            $this->service->acknowledge($current,$execution,RuntimeActionStatus::Completed,['client'=>'BROWSER']);
            $next=$this->service->active($luna);
            $this->assertNotNull($next);$this->assertSame($expected,$next->type);$actions[]=$next;
        }
        $last=$actions[3];$this->service->claim($last,'demo-exec-3','browser-demo');$this->service->acknowledge($last,'demo-exec-3',RuntimeActionStatus::Completed);
        $this->assertNull($this->service->active($luna));
        $this->assertCount(4,array_unique(array_map(fn($action)=>$action->id,$actions)));
    }
    private function aiAivva(){ $a=$this->makeLivingAivva(User::factory()->create());$a->control_mode=AivvaControlMode::AiTwin;$a->save();return $a; }
}
