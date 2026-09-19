<?php
namespace App\Domain\Runtime;

use App\Enums\{ActionType,AivvaControlMode,RuntimeActionType};
use App\Models\{Aivva,AivvaAction,AivvaRuntimeAction,AivvaRuntimeLocation};

class BrainRuntimeActionAdapter
{
    public function __construct(private readonly RuntimeActionService $runtime) {}
    public function shouldDispatch(Aivva $aivva,AivvaAction $action):bool{return (bool)config('aivva.ue.enabled')&&(bool)config('aivva.ue.ai_control_enabled')&&$aivva->control_mode===AivvaControlMode::AiTwin&&in_array($action->type,[ActionType::Travel,ActionType::Contact,ActionType::SendMessage],true);}
    public function dispatch(Aivva $aivva,AivvaAction $action):?AivvaRuntimeAction
    {
        if(!$this->shouldDispatch($aivva,$action))return null;
        $payload=$action->payload??[];
        if($action->type===ActionType::Travel){$location=AivvaRuntimeLocation::query()->where('logical_location_id',$payload['location_id']??null)->where('enabled',true)->first();abort_unless($location,422,'No trusted runtime location maps to this logical destination.');return $this->runtime->create($aivva,RuntimeActionType::MoveTo,['locationId'=>$location->id],'AI',$action->id);}
        if($action->type===ActionType::Contact)return $this->runtime->create($aivva,RuntimeActionType::Interact,['targetAivvaId'=>$payload['target_aivva_id']??null],'AI',$action->id);
        return $this->runtime->create($aivva,RuntimeActionType::Say,['text'=>(string)($payload['text']??$payload['message']??'')],'AI',$action->id);
    }
}
