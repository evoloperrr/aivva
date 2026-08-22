<?php

namespace App\Http\Controllers\Api;

use App\Domain\Chat\OwnerChatService;
use App\Http\Controllers\Controller;
use App\Models\Aivva;
use App\Models\OwnerChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private readonly OwnerChatService $chat) {}

    public function index(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);

        return response()->json([
            'data' => collect($this->chat->history($aivva))->map(fn (OwnerChat $row) => $this->serialize($row)),
        ]);
    }

    public function store(Request $request, Aivva $aivva): JsonResponse
    {
        abort_unless($aivva->owner_id === $request->user()->id, 403);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:800'],
        ]);

        $result = $this->chat->talk($aivva, $data['message']);

        return response()->json([
            'reply' => $this->serialize($result['reply']),
            'data' => collect($result['messages'])->map(fn (OwnerChat $row) => $this->serialize($row)),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(OwnerChat $row): array
    {
        return [
            'id' => $row->id,
            'role' => $row->role,
            'body' => $row->body,
            'intent' => $row->intent,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
