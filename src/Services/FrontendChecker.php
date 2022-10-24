<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Str;
use Illuminate\Routing\Route;

class FrontendChecker
{
    public function runningOnFrontend(): bool
    {
        $route = request()->route();

        if (!$route instanceof Route || blank($name = $route->getName())) {
            return false;
        }

        return Str::startsWith((string) $name, ['front.', 'api.']);
    }
}
