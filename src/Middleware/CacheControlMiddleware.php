<?php

namespace A17\CDN;

use Closure;
use A17\CDN\CacheControl;

class CacheControlMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        return $this->responseCanBeCached($response, $request)
            ? CacheControl::cache($response)
            : CacheControl::noCache($response);
    }

    protected function responseCanBeCached($response, $request): bool
    {
        return CDN::responseIsCachable($response) &&
            CDN::routeIsCachable() &&
            CDN::methodIsCachable() &&
            CDN::statusCodeIsCachable();
    }
}
