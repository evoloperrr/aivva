<?php

namespace Tests;

use App\Domain\Aivva\AivvaService;
use App\Models\Aivva;
use App\Models\User;
use Database\Seeders\PlatformCivilizationSeeder;
use Database\Seeders\WorldSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedCivilization(): void
    {
        $this->seed(WorldSeeder::class);
        $this->seed(PlatformCivilizationSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeLivingAivva(?User $owner = null, array $overrides = []): Aivva
    {
        $owner ??= User::factory()->create();

        return app(AivvaService::class)->create($owner, array_merge([
            'name' => 'LUNA',
            'personality' => 'Warm, precise, and unwilling to deceive.',
            'skills' => ['music', 'writing'],
            'interests' => ['sound', 'stories'],
            'risk_tolerance' => 'moderate',
            'autonomy_level' => 3,
        ], $overrides));
    }
}
