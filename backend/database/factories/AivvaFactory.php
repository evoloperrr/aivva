<?php

namespace Database\Factories;

use App\Enums\AivvaStatus;
use App\Models\Aivva;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Aivva>
 */
class AivvaFactory extends Factory
{
    protected $model = Aivva::class;

    public function definition(): array
    {
        $name = fake()->firstName();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => AivvaStatus::Idle,
            'current_location_id' => Location::query()->where('is_home_template', true)->value('id'),
            'home_location_id' => Location::query()->where('is_home_template', true)->value('id'),
            'energy' => 100,
            'world_minutes' => 480,
            'activated_at' => now(),
        ];
    }
}
