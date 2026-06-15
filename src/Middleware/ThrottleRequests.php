<?php

declare(strict_types=1);

namespace Ironflow\Middleware;

use Ironflow\Application;
use Ironflow\Exceptions\HttpException;
use Ironflow\Http\Request;
use Ironflow\RateLimiting\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sliding-window request throttling.
 *
 * Usage: ->middleware('throttle:60,1') = 60 requests per 1 minute.
 *
 * The limiter key combines client IP + route so each endpoint is metered
 * independently. Authenticated requests are keyed by user id when available,
 * preventing shared-NAT clients from exhausting each other's quota.
 *
 * On limit, responds 429 with a Retry-After header. Successful responses
 * carry X-RateLimit-Limit / X-RateLimit-Remaining.
 */
class ThrottleRequests
{
    public function handle(Request $request, callable $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $limiter = Application::getInstance()->getContainer()->make(RateLimiter::class);

        $key = $this->resolveKey($request);
        $decaySeconds = $decayMinutes * 60;

        if ($limiter->tooManyAttempts($key, $maxAttempts, $decaySeconds)) {
            $retryAfter = $limiter->availableIn($key, $maxAttempts, $decaySeconds);
            $e = new HttpException(429, 'Too Many Requests.');
            // Surface Retry-After via the exception's headers if supported.
            throw $e->withHeaders([
                'Retry-After'           => (string) $retryAfter,
                'X-RateLimit-Limit'     => (string) $maxAttempts,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        $limiter->hit($key, $decaySeconds);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set(
            'X-RateLimit-Remaining',
            (string) $limiter->remaining($key, $maxAttempts, $decaySeconds)
        );

        return $response;
    }

    private function resolveKey(Request $request): string
    {
        $identifier = $request->getClientIp() ?? 'unknown';

        // Prefer a stable per-user key for authenticated requests.
        $user = $request->attributes->get('auth_user');
        if (is_object($user) && isset($user->id)) {
            $identifier = 'user:' . $user->id;
        }

        return 'throttle:' . $identifier . ':' . $request->getPathInfo();
    }
}
