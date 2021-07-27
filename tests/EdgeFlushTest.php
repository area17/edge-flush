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

        EdgeFlush::setRequest(new \Illuminate\Http\Request());
    }

    protected $enabledValues = [
        'enabled' => false,
        'isCachable' => false,
        'routeIsCachable' => true,
        'max-age 1' => 604800000,
        'max-age 2' => 2500,
        'max-age 3' => 1500,
        'max-age' => [
            'enabled' => false,
            'isFrontend' => false,
            'notValidForm' => true,
            'methodIsCachable' => true,
            'middlewareAllowCaching' => true,
            'routeIsCachable' => true,
            'urlIsCachable' => true,
            'responseIsCachable' => true,
            'statusCodeIsCachable' => true,
        ],
        'headers' => [
            'cache-control' => ['max-age=5, public'],
            'content-type' => ['application/json'],
            'x-cache-control' => ['max-age=5, public'],
        ],
        'strategy 1' => 'max-age=5, public',
        'strategy 2' => 'max-age=5, public',
        'strategy 3' => 'max-age=20, no-store, public',
        0 => true,
    ];

    protected $disabledValues = [
        'enabled' => false,
        'isCachable' => false,
        'routeIsCachable' => true,
        'max-age 1' => 604800000,
        'max-age 2' => 2500,
        'max-age 3' => 1500,
        'max-age' => [
            'enabled' => false,
            'isFrontend' => false,
            'notValidForm' => true,
            'methodIsCachable' => true,
            'middlewareAllowCaching' => true,
            'routeIsCachable' => true,
            'urlIsCachable' => true,
            'responseIsCachable' => true,
            'statusCodeIsCachable' => true,
        ],
        'headers' => [
            'cache-control' => ['max-age=5, public'],
            'content-type' => ['application/json'],
            'x-cache-control' => ['max-age=5, public'],
        ],
        'strategy 1' => 'max-age=5, public',
        'strategy 2' => 'max-age=5, public',
        'strategy 3' => 'max-age=20, no-store, public',
        0 => true,
    ];

    /** @test */
    public function edge_flush_can_be_enabled()
    {
        $response = response()->json([]);

        $this->assertEquals($this->getValues($response), $this->enabledValues);
    }

    /** @test */
    public function edge_flush_can_be_disabled()
    {
        $response = response()->json([]);

        config(['edge-flush.enabled' => false]);

        $this->assertEquals($this->getValues($response), $this->disabledValues);
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

            'max-age' => CacheControl::setMaxAge(1600)->getMaxAge(),

            'max-age' => CacheControl::getCachableMatrix($response)->toArray(),

            'headers' => $this->extractHeaders(
                CacheControl::addHeadersToResponse(
                    $response,
                    'cache-control',
                    CacheControl::getCacheStrategy($response),
                )->headers->all(),
            ),

            'strategy 1' => CacheControl::getCacheStrategy($response),

            'strategy 2' => CacheControl::setStrategy(
                'micro-cache',
            )->getCacheStrategy($response),

            'strategy 3' => CacheControl::setStrategy('api')->getCacheStrategy(
                $response,
            ),

            fnmatch('newsletter*', 'newsletter'),
        ];
    }

    public function extractHeaders($headers)
    {
        return collect($headers)
            ->except('date')
            ->toArray();
    }
}
