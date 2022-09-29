<?php declare(strict_types=1);

namespace A17\EdgeFlush;

use Illuminate\Http\Request;
use A17\EdgeFlush\Services\Tags;
use A17\EdgeFlush\Services\Warmer;
use Illuminate\Support\Facades\Facade;
use A17\EdgeFlush\Contracts\CDNService;
use A17\EdgeFlush\Services\CacheControl;
use Symfony\Component\HttpFoundation\Response;

/**
 * @method static Response makeResponse(Response $response)
 * @method static CDNService cdn()
 * @method static CacheControl cacheControl()
 * @method static Tags tags()
 * @method static Warmer warmer()
 * @method static Request getRequest()
 * @method static bool enabled()
 * @method static self instance()
 * @method static self setRequest(Request $request)
 * @method static bool invalidationServiceIsEnabled()
 * @method static bool storeTagsServiceIsEnabled()
 **/
class EdgeFlush extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'a17.edge-flush.service';
    }
}
