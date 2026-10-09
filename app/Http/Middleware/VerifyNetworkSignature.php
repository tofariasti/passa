<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyNetworkSignature
{
    private const int TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-Network-Timestamp');
        $signature = $request->header('X-Network-Signature');

        if (! is_string($timestamp) || preg_match('/^\d+$/', $timestamp) !== 1 || ! is_string($signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $expected = 'sha256='.hash_hmac(
            'sha256',
            $timestamp.'.'.$request->getContent(),
            (string) config('services.network.secret'),
        );

        $fresh = abs(now()->getTimestamp() - (int) $timestamp) <= self::TOLERANCE_SECONDS;

        if (mb_strlen($signature) !== mb_strlen($expected) || ! hash_equals($expected, $signature) || ! $fresh) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        return $next($request);
    }
}
