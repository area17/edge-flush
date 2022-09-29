<?php

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Str;
use Illuminate\Routing\Route;

class FrontendChecker
{
    public function runningOnFrontend(): bool
    {
        $route = request()->route();

        if (!$route instanceof Route || empty(($name = $route->getName()))) {
            return false;
        }

        return Str::startsWith($name, ['front.', 'api.']);
    }
}
