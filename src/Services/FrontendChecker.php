<?php

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Str;

class FrontendChecker
{
    public function runningOnFrontend(): bool
    {
        return Str::startsWith(optional(request()->route())->getName(), [
            'front.',
            'api.',
        ]);
    }
}
