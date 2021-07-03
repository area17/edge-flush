<?php

namespace A17\CDN;

use Illuminate\Support\Facades\Facade;

class CDN extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'a17.cdn.service';
    }
}
