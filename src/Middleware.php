<?php declare(strict_types=1);

namespace A17\EdgeFlush;

use Closure;

class Middleware
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

        if ($this->enabled()) {
            return EdgeFlush::setRequest($request)->makeResponse($response);
        }

        return $response;
    }

    public function enabled(): bool
    {
        $enabled = config('edge-flush.enabled.package');

        return $enabled === true;
    }
}
