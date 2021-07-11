<?php

namespace A17\CDN\Services;

use Illuminate\Support\Str;

class FrontendChecker
{
    public function runningOnFrontend(): bool
    {
        /**
         * @psalm-suppress PossiblyInvalidMethodCall|PossiblyNullReference
         */
        return Str::startsWith(optional(request()->route())->getName(), [
            'front.',
            'api.',
        ]);
    }
}
