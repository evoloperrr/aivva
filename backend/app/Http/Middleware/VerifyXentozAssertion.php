<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class VerifyXentozAssertion
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('aivva.runtime.enabled')) {
            return new JsonResponse(['message' => 'Xentoz AIVVA integration is disabled.'], 503);
        }

        $secret = (string) config('aivva.runtime.integration_secret');
        $timestamp = (string) $request->header('X-Xentoz-Timestamp', '');
        $nonce = (string) $request->header('X-Xentoz-Nonce', '');
        $signature = strtolower((string) $request->header('X-Xentoz-Signature', ''));

        if ($secret === '' || ! ctype_digit($timestamp) || ! preg_match('/^[A-Za-z0-9_-]{20,64}$/', $nonce) || ! preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return new JsonResponse(['message' => 'Invalid Xentoz assertion.'], 401);
        }

        $now = now();
        if (abs($now->timestamp - (int) $timestamp) > (int) config('aivva.runtime.assertion_ttl_seconds', 300)) {
            return new JsonResponse(['message' => 'Expired Xentoz assertion.'], 401);
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            return new JsonResponse(['message' => 'Invalid Xentoz assertion.'], 401);
        }

        DB::table('xentoz_integration_nonces')->where('expires_at', '<', $now)->delete();
        $inserted = DB::table('xentoz_integration_nonces')->insertOrIgnore([
            'nonce' => $nonce,
            'expires_at' => $now->copy()->addSeconds((int) config('aivva.runtime.assertion_ttl_seconds', 300)),
            'created_at' => $now,
        ]);
        if ($inserted !== 1) {
            return new JsonResponse(['message' => 'Replayed Xentoz assertion.'], 409);
        }

        return $next($request);
    }
}
