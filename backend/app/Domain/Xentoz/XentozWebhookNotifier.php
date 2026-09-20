<?php

namespace App\Domain\Xentoz;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The reverse direction of the identity bridge: Xentoz signs assertions
 * into AIVVA (see VerifyXentozAssertion), and this is AIVVA signing a
 * notification back into Xentoz with the same shared secret and the same
 * timestamp.nonce.body HMAC scheme, verified on the Xentoz side by
 * verify-aivva-assertion.ts. Without this, a meetup request/accept was
 * only ever a silent row in AIVVA's own DB -- the recipient had no way to
 * find out short of manually opening the Meetups panel on the off chance
 * they thought to check it.
 *
 * Best-effort only: a failed or slow webhook call must never block the
 * actual meetup action it's reporting on.
 */
class XentozWebhookNotifier
{
    public function notify(?string $xentozUserId, string $type, string $title, string $body): void
    {
        if (! $xentozUserId) {
            return;
        }

        $secret = (string) config('aivva.runtime.integration_secret');
        $baseUrl = (string) config('aivva.xentoz_webhook_url');
        if ($secret === '' || $baseUrl === '') {
            return;
        }

        $payload = json_encode([
            'xentozUserId' => $xentozUserId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ], JSON_UNESCAPED_SLASHES);

        $timestamp = (string) now()->timestamp;
        $nonce = Str::random(32);
        $signature = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$payload, $secret);

        try {
            Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Aivva-Timestamp' => $timestamp,
                'X-Aivva-Nonce' => $nonce,
                'X-Aivva-Signature' => $signature,
            ])->timeout(5)->withBody($payload, 'application/json')
                ->post(rtrim($baseUrl, '/').'/api/aivva/webhooks/meetup-event');
        } catch (Throwable $e) {
            Log::warning('AIVVA_XENTOZ_NOTIFY_FAILED', ['type' => $type, 'error' => $e->getMessage()]);
        }
    }
}
