<?php

namespace A17\CDN\Tests;

use A17\CDN\CDN;
use A17\CDN\CacheControl;

class CDNTest extends TestCase
{
    protected $enabledValues = [
        'enabled' => true,
        'isCachable' => true,
        'routeIsCachable' => true,
        'max-age 1' => 604800,
        'max-age 2' => 2500,
        'max-age 3' => 1500,
        'max-age' => [
            'enabled' => true,
            'isFrontend' => true,
            'notValidForm' => true,
            'middlewareAllowCaching' => true,
            'routeIsCachable' => true,
            'responseIsCachable' => true,
            'methodIsCachable' => true,
            'statusCodeIsCachable' => true,
        ],
        'headers' => [
            'cache-control' => [
                'max-age=1500, must-revalidate, no-store, public',
            ],
            'content-type' => ['application/json'],
            'x-cache-control' => [
                'max-age=1500, must-revalidate, no-store, public',
            ],
        ],
        'strategy 1' => 'max-age=1500, must-revalidate, no-store, public',
        'strategy 2' => 'no-store, private',
        'strategy 3' => 'max-age=20, no-store, public',
        0 => true,
    ];

    protected $disabledValues = [
        'enabled' => false,
        'isCachable' => false,
        'routeIsCachable' => true,
        'max-age 1' => 604800,
        'max-age 2' => 2500,
        'max-age 3' => 1500,
        'max-age' => [
            'enabled' => false,
            'isFrontend' => true,
            'notValidForm' => true,
            'middlewareAllowCaching' => true,
            'routeIsCachable' => true,
            'responseIsCachable' => true,
            'methodIsCachable' => true,
            'statusCodeIsCachable' => true,
        ],
        'headers' => [
            'cache-control' => ['no-store, private'],
            'content-type' => ['application/json'],
            'x-cache-control' => ['no-store, private'],
        ],
        'strategy 1' => 'no-store, private',
        'strategy 2' => 'no-store, private',
        'strategy 3' => 'max-age=20, no-store, public',
        0 => true,
    ];

    /** @test */
    public function cdn_can_be_enabled()
    {
        $response = response()->json([]);

        $this->assertEquals($this->getValues($response), $this->enabledValues);
    }

    /** @test */
    public function cdn_can_be_disabled()
    {
        $response = response()->json([]);

        config(['cdn.enabled' => false]);

        $this->assertEquals($this->getValues($response), $this->disabledValues);
    }

    public function getValues($response)
    {
        return [
            'enabled' => CDN::enabled(),

            'isCachable' => CacheControl::isCachable($response),

            'routeIsCachable' => CacheControl::routeIsCachable(),

            'enabled' => CDN::enabled(),

            'max-age 1' => CacheControl::getMaxAge(),

            'max-age 2' => CacheControl::setMaxAge(2500)->getMaxAge(),

            'max-age 3' => CacheControl::setMaxAge(1500)->getMaxAge(),

            'max-age' => CacheControl::setMaxAge(1600)->getMaxAge(),

            'max-age' => CacheControl::getCachableMatrix($response)->toArray(),

            'headers' => $this->extractHeaders(
                CacheControl::addHttpHeadersToResponse(
                    $response,
                )->headers->all(),
            ),

            'strategy 1' => CacheControl::getCacheStrategy($response),

            'strategy 2' => CacheControl::setStrategy(
                'do-not-cache',
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
