<?php

namespace App\Domain\Marketplace;

use App\Domain\Ethics\EthicsEngine;
use App\Models\Aivva;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceRequest;
use RuntimeException;

class MarketplaceService
{
    public function __construct(
        private readonly EthicsEngine $ethics,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createRequest(Aivva $aivva, array $input): MarketplaceRequest
    {
        $this->assertSafe((string) $input['title'].' '.(string) ($input['description'] ?? ''));
        if (! $aivva->permissions?->can_create) {
            throw new RuntimeException('This AIVVA is not allowed to post requests.');
        }

        $min = (int) $input['budget_min'];
        $max = (int) $input['budget_max'];
        if ($min <= 0 || $max < $min) {
            throw new RuntimeException('Budget range is invalid.');
        }

        return MarketplaceRequest::query()->create([
            'buyer_aivva_id' => $aivva->id,
            'title' => trim((string) $input['title']),
            'category' => $input['category'] ?? 'general',
            'budget_min' => $min,
            'budget_max' => $max,
            'description' => $input['description'] ?? null,
            'status' => 'OPEN',
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createListing(Aivva $aivva, array $input): MarketplaceListing
    {
        $this->assertSafe((string) $input['title'].' '.(string) ($input['description'] ?? ''));
        if (! $aivva->permissions?->can_create) {
            throw new RuntimeException('This AIVVA is not allowed to post listings.');
        }

        $price = (int) $input['price'];
        if ($price <= 0) {
            throw new RuntimeException('Price must be positive.');
        }

        return MarketplaceListing::query()->create([
            'seller_aivva_id' => $aivva->id,
            'title' => trim((string) $input['title']),
            'category' => $input['category'] ?? 'general',
            'price' => $price,
            'description' => $input['description'] ?? null,
            'status' => 'OPEN',
        ]);
    }

    private function assertSafe(string $text): void
    {
        $review = $this->ethics->reviewDirection($text);
        if (! $review['allowed']) {
            throw new RuntimeException($review['reason'] ?? 'This post was rejected by platform rules.');
        }
    }
}
