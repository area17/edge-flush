<?php

namespace App\Services\CDN;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CacheControlMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        return $this->responseCanBeCached($response, $request)
            ? cache_control()->cache($response)
            : cache_control()->noCache($response);
    }

    private function responseCanBeCached($response, $request): bool
    {
        if (
            ! (
                $response instanceof Response ||
                $response instanceof JsonResponse
            )
        ) {
            return false;
        }

        if ($request->getMethod() !== 'GET') {
            return false;
        }

        return $response->getStatusCode() < 400;
    }
}
