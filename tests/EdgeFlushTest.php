<?php

namespace A17\EdgeFlush\Tests;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\CacheControl;
use A17\EdgeFlush\ServiceProvider;

class EdgeFlushTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config(['edge-flush.enabled' => true]);

        EdgeFlush::setRequest(new \Illuminate\Http\Request());
    }

    protected $enabledValues = [
        'enabled' => true,
        'isCachable' => false,
        'routeIsCachable' => true,
        'max-age 1' => 0,
        'max-age 2' => 2500,
        'max-age 3' => 1500,
        'max-age 4' => 1500,
        'max-age 5' => [
            'enabled' => true,
            'isFrontend' => false,
            'notValidForm' => true,
            'methodIsCachable' => true,
            'middlewareAllowCaching' => true,
            'routeIsCachable' => true,
            'urlIsCachable' => true,
            'responseIsCachable' => true,
            'statusCodeIsCachable' => true,
        ],
        's-maxage' => 2000,
        'headers' => [
            'cache-control' => ['max-age=0, public, s-maxage=5'],
            'content-type' => ['application/json'],
            'x-cache-control' => ['max-age=0, public, s-maxage=5'],
        ],
        'strategy dynamic' => 'max-age=0, public, s-maxage=5',
        'strategy zero' => 'max-age=0, no-store, s-maxage=0',
        'strategy micro' => 'max-age=0, public, s-maxage=5',
        'strategy small' => 'max-age=0, public, s-maxage=120',
        'strategy large' => 'max-age=0, public, s-maxage=604800',
        'strategy api' => 'max-age=0, no-store, public, s-maxage=20',
        'fnmatch' => true,
    ];

    protected $disabledValues = [
        'enabled' => false,
        'isCachable' => false,
        'routeIsCachable' => false,
        'max-age 1' => 0,
        'max-age 2' => 0,
        'max-age 3' => 0,
        'max-age 4' => 0,
        'max-age 5' => [
            'enabled' => false,
            'isFrontend' => false,
            'notValidForm' => true,
            'methodIsCachable' => false,
            'middlewareAllowCaching' => false,
            'routeIsCachable' => false,
            'urlIsCachable' => false,
            'responseIsCachable' => false,
            'statusCodeIsCachable' => false,
        ],
        's-maxage' => 0,
        'headers' => [
            'cache-control' => ['no-cache, private'],
            'content-type' => ['application/json'],
        ],
        'strategy dynamic' => 'max-age=0, no-store, s-maxage=0',
        'strategy zero' => 'max-age=0, no-store, s-maxage=0',
        'strategy micro' => 'max-age=0, no-store, s-maxage=0',
        'strategy small' => 'max-age=0, no-store, s-maxage=0',
        'strategy large' => 'max-age=0, no-store, s-maxage=0',
        'strategy api' => 'max-age=0, no-store, s-maxage=0',
        'fnmatch' => true,
    ];

    /** @test */
    public function edge_flush_can_be_enabled()
    {
        EdgeFlush::enable();

        $response = response()->json([]);

        $this->assertEquals($this->enabledValues, $this->getValues($response));
    }

    /** @test */
    public function edge_flush_can_be_disabled()
    {
        EdgeFlush::disable();

        $response = response()->json([]);

        config(['edge-flush.enabled' => false]);

        $this->assertEquals($this->disabledValues, $this->getValues($response));
    }

    public function getValues($response)
    {
        return [
            'enabled' => EdgeFlush::enabled(),

            'isCachable' => CacheControl::isCachable($response),

            'routeIsCachable' => CacheControl::routeIsCachable(),

            'enabled' => EdgeFlush::enabled(),

            'max-age 1' => CacheControl::getMaxAge(),

            'max-age 2' => CacheControl::setMaxAge(2500)->getMaxAge(),

            'max-age 3' => CacheControl::setMaxAge(1500)->getMaxAge(),

            'max-age 4' => CacheControl::setMaxAge(1600)->getMaxAge(), /// the min strategy should keep it at 1500s

            'max-age 5' => CacheControl::getCachableMatrix(
                $response,
            )->toArray(),

            's-maxage' => CacheControl::setSMaxAge(2000)->getSMaxAge(),

            'headers' => $this->extractHeaders(
                CacheControl::addHeadersToResponse(
                    $response,
                    'cache-control',
                    CacheControl::getCacheStrategy($response),
                )->headers->all(),
            ),

            'strategy dynamic' => CacheControl::getCacheStrategy($response),

            'strategy zero' => CacheControl::setStrategy(
                'zero',
            )->getCacheStrategy($response),

            'strategy micro' => CacheControl::setStrategy(
                'micro',
            )->getCacheStrategy($response),

            'strategy small' => CacheControl::setStrategy(
                'small',
            )->getCacheStrategy($response),

            'strategy large' => CacheControl::setStrategy(
                'large',
            )->getCacheStrategy($response),

            'strategy api' => CacheControl::setStrategy(
                'api',
            )->getCacheStrategy($response),

            'fnmatch' => fnmatch('newsletter*', 'newsletter'),
        ];
    }

    public function extractHeaders($headers)
    {
        return collect($headers)
            ->except('date')
            ->toArray();
    }
}
