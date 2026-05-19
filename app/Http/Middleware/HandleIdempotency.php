<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class HandleIdempotency
{
    private const TTL_SECONDS = 86_400; // 24 hours

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (blank($key)) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cacheKey = $this->cacheKey($request, $key);

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return response()->json(
                $cached['body'],
                $cached['status'],
                ['X-Idempotent-Replayed' => 'true'],
            );
        }

        /** @var JsonResponse $response */
        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body'   => $response->getData(assoc: true),
            ], self::TTL_SECONDS);
        }

        return $response;
    }

    private function cacheKey(Request $request, string $idempotencyKey): string
    {
        $userId = $request->user()?->id ?? 'guest';

        return "http:idempotency:{$userId}:{$idempotencyKey}";
    }
}
