<?php

namespace A17\EdgeFlush;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Symfony\Component\HttpFoundation\Response makeResponse(Response $response)
 * @method static \A17\EdgeFlush\Contracts\CDNService cdn()
 * @method static \A17\EdgeFlush\Services\CacheControl cacheControl()
 * @method static \A17\EdgeFlush\Services\Tags tags()
 * @method static \A17\EdgeFlush\Services\Warmer warmer()
 * @method static \Illuminate\Http\Request\Request getRequest()
 * @method static bool enabled()
 * @method static self instance()
 * @method static self setRequest(Request $request)
 **/
class EdgeFlush extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'a17.edge-flush.service';
    }
}
