<?php

namespace A17\EdgeFlush;

use Illuminate\Support\Facades\Facade;

class CacheControl extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'a17.edge-flush.cache-control';
    }
}
