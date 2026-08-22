<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_create_aivva(): void
    {
        $this->seedCivilization();

        $register = $this->postJson('/api/auth/register', [
            'name' => 'Mira',
            'email' => 'mira@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $register->assertCreated();
        $token = $register->json('token');

        $create = $this->withToken($token)->postJson('/api/aivvas', [
            'name' => 'LUNA',
            'personality' => 'Curious and ethical.',
            'skills' => ['music', 'writing'],
            'interests' => ['sound'],
            'risk_tolerance' => 'moderate',
        ]);

        $create->assertCreated()->assertJsonPath('data.name', 'LUNA');
        $this->assertSame(100, $create->json('data.wallet.available'));
    }

    public function test_owner_cannot_read_another_aivva(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $aivva = $this->makeLivingAivva($owner);

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/aivvas/'.$aivva->id)
            ->assertForbidden();
    }
}
