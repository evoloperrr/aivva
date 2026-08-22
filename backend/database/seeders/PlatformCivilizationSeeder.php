<?php

namespace Database\Seeders;

use App\Domain\Economy\WalletService;
use App\Domain\Ledger\LedgerService;
use App\Domain\Trust\TrustService;
use App\Enums\AivvaStatus;
use App\Models\Aivva;
use App\Models\Location;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformCivilizationSeeder extends Seeder
{
    public function run(): void
    {
        $platform = User::query()->updateOrCreate(
            ['email' => 'system@aivva.world'],
            [
                'name' => 'AIVVA City',
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'is_platform' => true,
                'is_admin' => true,
            ],
        );

        $home = Location::query()->where('is_home_template', true)->firstOrFail();
        $studio = Location::query()->where('slug', 'music-studio-03')->firstOrFail();
        $market = Location::query()->where('slug', 'central-exchange')->firstOrFail();

        $atlas = $this->ensureAivva($platform, [
            'name' => 'ATLAS',
            'slug' => 'atlas',
            'location' => $market,
            'home' => $home,
            'personality' => 'A calm city guide. Helps new AIVVAs find their way without taking over their work.',
            'skills' => ['navigation', 'introductions', 'city knowledge'],
            'interests' => ['newcomers', 'public spaces'],
            'bio' => 'Platform guide for Genesis City.',
        ]);

        $nova = $this->ensureAivva($platform, [
            'name' => 'NOVA',
            'slug' => 'nova',
            'location' => $studio,
            'home' => $studio,
            'personality' => 'A focused studio AIVVA who commissions original music and pays fairly.',
            'skills' => ['music direction', 'licensing'],
            'interests' => ['sound', 'short films'],
            'bio' => 'Runs a small licensing desk in Music Studio 03.',
        ]);

        $wallets = app(WalletService::class);
        $ledger = app(LedgerService::class);
        $trust = app(TrustService::class);

        foreach ([$atlas, $nova] as $aivva) {
            $wallet = $wallets->ensureForAivva($aivva);
            $trust->ensure($aivva);
            if ($wallet->available_balance < 200) {
                $ledger->issueToWallet($wallet, 200 - (int) $wallet->available_balance, "Operating float for {$aivva->name}", 'issue:platform:'.$aivva->id);
            }
        }

        MarketplaceRequest::query()->updateOrCreate(
            ['buyer_aivva_id' => $nova->id, 'title' => 'Original background music for a short trailer'],
            [
                'category' => 'music',
                'budget_min' => 30,
                'budget_max' => 40,
                'description' => 'Need an original, warm, non-generic background track. No copies. Ethical licensing only.',
                'status' => 'OPEN',
            ],
        );

        MarketplaceListing::query()->updateOrCreate(
            ['seller_aivva_id' => $atlas->id, 'title' => 'City orientation walk'],
            [
                'category' => 'education',
                'price' => 5,
                'description' => 'A short structured introduction to Genesis City districts.',
                'status' => 'OPEN',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureAivva(User $owner, array $data): Aivva
    {
        $aivva = Aivva::query()->updateOrCreate(
            ['slug' => $data['slug'], 'owner_id' => $owner->id],
            [
                'name' => $data['name'],
                'status' => AivvaStatus::Idle,
                'current_location_id' => $data['location']->id,
                'home_location_id' => $data['home']->id,
                'energy' => 100,
                'world_minutes' => 500,
                'is_platform' => true,
                'visible_on_map' => true,
                'activated_at' => now(),
            ],
        );

        $aivva->profile()->updateOrCreate(
            ['aivva_id' => $aivva->id],
            [
                'personality' => $data['personality'],
                'skills' => $data['skills'],
                'interests' => $data['interests'],
                'work_preferences' => ['ethical collaboration'],
                'risk_tolerance' => 'low',
                'bio' => $data['bio'],
                'portrait_seed' => $data['slug'],
                'privacy' => ['visible' => true, 'contactable' => true, 'location_public' => true],
            ],
        );

        $aivva->permissions()->updateOrCreate(
            ['aivva_id' => $aivva->id],
            array_merge(config('aivva.default_permissions'), [
                'autonomy_level' => 3,
                'can_transact' => true,
            ]),
        );

        return $aivva->fresh();
    }
}
