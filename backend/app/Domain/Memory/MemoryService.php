<?php

namespace App\Domain\Memory;

use App\Enums\MemoryCategory;
use App\Models\Aivva;
use App\Models\AivvaMemory;

class MemoryService
{
    /**
     * @param  array<string, mixed>  $related
     */
    public function remember(
        Aivva $aivva,
        MemoryCategory $category,
        string $content,
        int $importance = 3,
        array $related = [],
        bool $isPrivate = true,
    ): AivvaMemory {
        return AivvaMemory::query()->create([
            'aivva_id' => $aivva->id,
            'category' => $category,
            'content' => $content,
            'importance' => $importance,
            'related' => $related,
            'is_private' => $isPrivate,
            'expires_at' => $category === MemoryCategory::ShortTerm ? now()->addHours(36) : null,
        ]);
    }

    /**
     * @return list<AivvaMemory>
     */
    public function important(Aivva $aivva, int $limit = 8): array
    {
        return AivvaMemory::query()
            ->where('aivva_id', $aivva->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('importance')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}
