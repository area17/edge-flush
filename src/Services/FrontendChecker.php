<?php

namespace A17\CDN\Services;

use Illuminate\Support\Str;

class FrontendChecker
{
    public function runningOnFrontend()
    {
        return Str::startsWith(optional(request()->route())->getName(), [
            'front.',
            'api.',
        ]);
    }
}
