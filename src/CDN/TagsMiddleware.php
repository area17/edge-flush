<?php

namespace App\Services\CDN;

use Closure;

class TagsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        return app(Tags::class)->addHttpHeadersToResponse($response);
    }
}
