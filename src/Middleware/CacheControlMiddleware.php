<?php

namespace A17\CDN;

use Closure;

class CacheControlMiddleware
{
    public function handle($request, Closure $next)
    {
        response()
        return CacheControl::buildResponse($next($request));
    }
}
