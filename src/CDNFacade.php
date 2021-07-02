<?php

namespace Area17\CDN;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Area17\CDN\CDN
 */
class CDNFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cdn';
    }
}
