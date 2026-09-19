<?php
namespace App\Http\Controllers\Api;

use App\Domain\Runtime\RuntimeActionService;
use App\Enums\RuntimeActionStatus;
use App\Http\Controllers\Controller;
use App\Models\{Aivva,AivvaRuntimeAction};
use Illuminate\Http\{JsonResponse,Request};

class RuntimeActionController extends Controller
{
    public function __construct(private readonly RuntimeActionService $actions) {}
    public function active(Request $r,Aivva $a):JsonResponse{$this->authorizeRuntime($r,$a);return response()->json(['data'=>$this->serialize($this->actions->active($a))]);}
    public function claim(Request $r,Aivva $a,AivvaRuntimeAction $runtimeAction):JsonResponse{$this->authorizeAction($r,$a,$runtimeAction);$d=$r->validate(['executionId'=>['required','string','max:100'],'clientInstanceId'=>['required','string','max:100']]);return response()->json(['data'=>$this->serialize($this->actions->claim($runtimeAction,$d['executionId'],$d['clientInstanceId']))]);}
    public function status(Request $r,Aivva $a,AivvaRuntimeAction $runtimeAction):JsonResponse{$this->authorizeAction($r,$a,$runtimeAction);$d=$r->validate(['executionId'=>['required','string','max:100'],'status'=>['required','in:COMPLETED,FAILED,CANCELLED'],'failureCode'=>['nullable','string','max:80'],'result'=>['nullable','array']]);return response()->json(['data'=>$this->serialize($this->actions->acknowledge($runtimeAction,$d['executionId'],RuntimeActionStatus::from($d['status']),$d['result']??[],$d['failureCode']??null))]);}
    public function cancel(Request $r,Aivva $a,AivvaRuntimeAction $runtimeAction):JsonResponse{$this->authorizeAction($r,$a,$runtimeAction);return response()->json(['data'=>$this->serialize($this->actions->cancel($runtimeAction))]);}
    private function authorizeRuntime(Request $r,Aivva $a):void{abort_unless(config('aivva.ue.enabled'),503);abort_unless($r->user()?->tokenCan('aivva:runtime'),403);abort_unless($a->owner_id===$r->user()?->id,403);}
    private function authorizeAction(Request $r,Aivva $a,AivvaRuntimeAction $x):void{$this->authorizeRuntime($r,$a);abort_unless($x->aivva_id===$a->id,404);}
    private function serialize(?AivvaRuntimeAction $a):?array{if(!$a)return null;return ['actionId'=>$a->id,'aivvaId'=>$a->aivva_id,'type'=>$a->type->value,'payload'=>$a->payload,'status'=>$a->status->value,'createdAt'=>$a->created_at?->toIso8601String(),'startedAt'=>$a->started_at?->toIso8601String(),'completedAt'=>$a->completed_at?->toIso8601String(),'failureCode'=>$a->failure_code,'correlationId'=>$a->correlation_id,'executionId'=>$a->execution_id,'leaseExpiresAt'=>$a->lease_expires_at?->toIso8601String(),'expiresAt'=>$a->expires_at?->toIso8601String(),'version'=>$a->version];}
}
