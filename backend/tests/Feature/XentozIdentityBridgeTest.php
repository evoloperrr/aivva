<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\XentozIdentityLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class XentozIdentityBridgeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-only-integration-secret-with-enough-entropy';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('aivva.ue.enabled', true);
        config()->set('aivva.ue.integration_secret', self::SECRET);
        config()->set('aivva.ue.assertion_ttl_seconds', 300);
        config()->set('aivva.ue.token_ttl_minutes', 15);
    }

    public function test_bridge_is_unavailable_when_feature_is_disabled(): void
    {
        config()->set('aivva.ue.enabled', false);
        $this->signedRequest($this->payload(), 'disabled-feature-nonce')->assertStatus(503);
    }

    public function test_valid_assertion_links_existing_owner_and_issues_scoped_short_lived_token(): void
    {
        $this->seedCivilization();
        $owner = User::factory()->create(['email' => 'owner@xentoz.test']);
        $aivva = $this->makeLivingAivva($owner);

        $response = $this->signedRequest($this->payload(), 'valid-identity-nonce');

        $response->assertOk()
            ->assertJsonPath('xentozUserId', 'usr_xentoz_123')
            ->assertJsonPath('tokenType', 'Bearer')
            ->assertJsonPath('characters.0.id', $aivva->id);
        $this->assertNotEmpty($response->json('accessToken'));
        $this->assertDatabaseHas('xentoz_identity_links', ['user_id' => $owner->id, 'xentoz_user_id' => 'usr_xentoz_123']);

        $token = $owner->tokens()->latest()->firstOrFail();
        $this->assertTrue($token->can('aivva:read'));
        $this->assertTrue($token->can('aivva:runtime'));
        $this->assertFalse($token->can('wallet:write'));
        $this->assertNotNull($token->expires_at);
    }

    public function test_same_xentoz_identity_reuses_stable_link(): void
    {
        User::factory()->create(['email' => 'owner@xentoz.test']);
        $this->signedRequest($this->payload(), 'first-stable-link-nonce')->assertOk();
        $this->signedRequest($this->payload(), 'second-stable-link-nonce')->assertOk();
        $this->assertSame(1, XentozIdentityLink::query()->count());
    }

    public function test_replayed_assertion_is_rejected(): void
    {
        User::factory()->create(['email' => 'owner@xentoz.test']);
        $timestamp = (string) now()->timestamp;
        $this->signedRequest($this->payload(), 'one-time-replay-nonce', $timestamp)->assertOk();
        $this->signedRequest($this->payload(), 'one-time-replay-nonce', $timestamp)->assertStatus(409);
    }

    public function test_modified_body_with_old_signature_is_rejected(): void
    {
        $payload = $this->payload();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $nonce = 'tamper-resistant-nonce';
        $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, self::SECRET);
        $payload['email'] = 'attacker@xentoz.test';

        $this->call('POST', '/api/integrations/xentoz/session', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_XENTOZ_TIMESTAMP' => $timestamp,
            'HTTP_X_XENTOZ_NONCE' => $nonce,
            'HTTP_X_XENTOZ_SIGNATURE' => $signature,
        ], json_encode($payload, JSON_THROW_ON_ERROR))->assertUnauthorized();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['xentozUserId' => 'usr_xentoz_123', 'name' => 'Owner', 'email' => 'owner@xentoz.test', 'emailVerified' => true];
    }

    /** @param array<string, mixed> $payload */
    private function signedRequest(array $payload, string $nonce, ?string $timestamp = null): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$body, self::SECRET);

        return $this->call('POST', '/api/integrations/xentoz/session', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_XENTOZ_TIMESTAMP' => $timestamp,
            'HTTP_X_XENTOZ_NONCE' => $nonce,
            'HTTP_X_XENTOZ_SIGNATURE' => $signature,
        ], $body);
    }
}
