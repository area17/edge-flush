<?php

namespace A17\CDN\Services;

class Service
{
    public function responseIsCachable($response): bool
    {
        return (collect(config('cdn.responses.cachable'))->isEmpty() ||
                collect(config('cdn.responses.cachable'))->contains(
                    get_class($response),
                )) &&
            !collect(config('cdn.responses.not-cachable'))->contains(
                get_class($response),
            );
    }

    public function methodIsCachable(): bool
    {
        return (collect(config('cdn.methods.cachable'))->isEmpty() ||
                collect(config('cdn.methods.cachable'))->contains(
                    $request->getMethod(),
                )) &&
            !collect(config('cdn.methods.not-cachable'))->contains(
                $request->getMethod(),
            );
    }

    public function statusCodeIsCachable(): bool
    {
        return (collect(config('cdn.statuses.cachable'))->isEmpty() ||
                collect(config('cdn.statuses.cachable'))->contains(
                    $response->getStatusCode(),
                )) &&
            !collect(config('cdn.statuses.not-cachable'))->contains(
                $response->getStatusCode(),
            );
    }

    public function routeCodeIsCachable(): bool
    {
        $route = request()
            ->route()
            ->getName();

        return (collect(config('cdn.routes.cachable'))->isEmpty() ||
                collect(config('cdn.routes.cachable'))->contains($route)) &&
            !collect(config('cdn.routes.not-cachable'))->contains($route);
    }
}
