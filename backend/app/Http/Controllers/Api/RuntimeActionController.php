<?php
namespace App\Http\Controllers\Api;

use App\Domain\Runtime\RuntimeActionService;
use App\Enums\RuntimeActionStatus;
use App\Enums\RuntimeActionType;
use App\Http\Controllers\Controller;
use App\Models\{Aivva,AivvaRuntimeAction};
use Illuminate\Http\{JsonResponse,Request};
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RuntimeActionController extends Controller
{
    public function __construct(private readonly RuntimeActionService $actions) {}
    public function health(Request $r):JsonResponse{abort_unless(config('aivva.runtime.enabled'),503,'AIVVA runtime is disabled.');abort_unless($r->user()?->tokenCan('aivva:runtime'),403,'Runtime scope required.');DB::select('select 1');return response()->json(['ok'=>true,'runtime'=>'enabled','authentication'=>'scoped-token','eventStreamSeconds'=>(int)config('aivva.runtime.event_stream_seconds',25),'serverTime'=>now()->toIso8601String()]);}
    public function active(Request $r,Aivva $aivva):JsonResponse{$this->authorizeRuntime($r,$aivva);return response()->json(['data'=>$this->serialize($this->actions->active($aivva))]);}
    public function startDemo(Request $r,Aivva $aivva):JsonResponse{$this->authorizeRuntime($r,$aivva);return response()->json(['data'=>$this->serialize($this->actions->startPlazaDemo($aivva))],201);}
    public function request(Request $r,Aivva $aivva):JsonResponse{$this->authorizeRuntime($r,$aivva);$d=$r->validate(['type'=>['required','in:MOVE_TO,SAY'],'payload'=>['required','array']]);abort_if($this->actions->active($aivva),409,'Finish or cancel the active action first.');$action=$this->actions->create($aivva,RuntimeActionType::from($d['type']),$d['payload'],'HUMAN');return response()->json(['data'=>$this->serialize($action)],201);}
    public function trace(Request $r,Aivva $aivva,AivvaRuntimeAction $runtimeAction):JsonResponse{$this->authorizeAction($r,$aivva,$runtimeAction);return response()->json(['data'=>$this->serialize($runtimeAction->fresh())]);}
    public function claim(Request $r,Aivva $aivva,AivvaRuntimeAction $runtimeAction):JsonResponse{$this->authorizeAction($r,$aivva,$runtimeAction);$d=$r->validate(['executionId'=>['required','string','max:100'],'clientInstanceId'=>['required','string','max:100']]);return response()->json(['data'=>$this->serialize($this->actions->claim($runtimeAction,$d['executionId'],$d['clientInstanceId']))]);}
    public function status(Request $r,Aivva $aivva,AivvaRuntimeAction $runtimeAction):JsonResponse{$this->authorizeAction($r,$aivva,$runtimeAction);$d=$r->validate(['executionId'=>['required','string','max:100'],'status'=>['required','in:COMPLETED,FAILED,CANCELLED'],'failureCode'=>['nullable','string','max:80'],'result'=>['nullable','array']]);return response()->json(['data'=>$this->serialize($this->actions->acknowledge($runtimeAction,$d['executionId'],RuntimeActionStatus::from($d['status']),$d['result']??[],$d['failureCode']??null))]);}
    public function cancel(Request $r,Aivva $aivva,AivvaRuntimeAction $runtimeAction):JsonResponse{$this->authorizeAction($r,$aivva,$runtimeAction);return response()->json(['data'=>$this->serialize($this->actions->cancel($runtimeAction))]);}
    public function events(Request $r,Aivva $aivva):StreamedResponse{$this->authorizeRuntime($r,$aivva);$lastVersion=max(0,(int)$r->query('afterVersion',0));$lastActionId=(string)$r->query('afterActionId','');return response()->stream(function()use($aivva,$lastVersion,$lastActionId){$deadline=microtime(true)+(int)config('aivva.runtime.event_stream_seconds',25);$seenVersion=$lastVersion;$seenId=$lastActionId;echo "retry: 1500\n\n";while(microtime(true)<$deadline&&!connection_aborted()){$action=AivvaRuntimeAction::query()->where('aivva_id',$aivva->id)->latest('updated_at')->first();if($action&&($action->id!==$seenId||(int)$action->version>$seenVersion)){$seenId=$action->id;$seenVersion=(int)$action->version;$event=match($action->status){RuntimeActionStatus::Requested=>'AIVVA_ACTION_REQUESTED',RuntimeActionStatus::Cancelled=>'AIVVA_ACTION_CANCELLED',RuntimeActionStatus::Expired=>'AIVVA_ACTION_EXPIRED',default=>'AIVVA_ACTION_UPDATED'};echo 'event: '.$event."\n".'data: '.json_encode($this->serialize($action),JSON_UNESCAPED_SLASHES)."\n\n";}else{echo ": heartbeat\n\n";}if(ob_get_level()>0)@ob_flush();flush();usleep(750000);}},200,['Content-Type'=>'text/event-stream','Cache-Control'=>'no-cache, no-store, private','X-Accel-Buffering'=>'no']);}
    private function authorizeRuntime(Request $r,Aivva $a):void{abort_unless(config('aivva.runtime.enabled'),503);abort_unless($r->user()?->tokenCan('aivva:runtime'),403);abort_unless($a->owner_id===$r->user()?->id,403);}
    private function authorizeAction(Request $r,Aivva $a,AivvaRuntimeAction $x):void{$this->authorizeRuntime($r,$a);abort_unless($x->aivva_id===$a->id,404);}
    private function serialize(?AivvaRuntimeAction $a):?array{if(!$a)return null;return ['actionId'=>$a->id,'aivvaId'=>$a->aivva_id,'type'=>$a->type->value,'payload'=>$a->payload,'status'=>$a->status->value,'createdAt'=>$a->created_at?->toIso8601String(),'claimedAt'=>$a->claimed_at?->toIso8601String(),'startedAt'=>$a->started_at?->toIso8601String(),'completedAt'=>$a->completed_at?->toIso8601String(),'failureCode'=>$a->failure_code,'correlationId'=>$a->correlation_id,'executionId'=>$a->execution_id,'leaseExpiresAt'=>$a->lease_expires_at?->toIso8601String(),'expiresAt'=>$a->expires_at?->toIso8601String(),'version'=>$a->version];}
}
