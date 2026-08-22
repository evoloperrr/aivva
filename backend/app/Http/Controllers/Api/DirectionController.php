<?php

namespace App\Http\Controllers\Api;

use App\Domain\Aivva\AivvaService;
use App\Http\Controllers\Controller;
use App\Http\Resources\AivvaResource;
use App\Models\Aivva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectionController extends Controller
{
    public function __construct(private readonly AivvaService $aivvas) {}

    public function interpret(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);
        $data = $request->validate([
            'direction' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $result = $this->aivvas->previewDirection($aivva, $data['direction']);

        return response()->json([
            'goal_id' => $result['goal']->id,
            'interpretation' => $result['interpretation'],
        ]);
    }

    public function confirm(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);
        $data = $request->validate([
            'goal_id' => ['required', 'uuid'],
        ]);

        $updated = $this->aivvas->confirmDirection($aivva, $data['goal_id']);

        return response()->json(['data' => new AivvaResource($updated)]);
    }
}
