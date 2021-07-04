<?php

use Illuminate\Support\Str;
use Illuminate\Http\Response;
use A17\CDN\Support\Constants;
use Illuminate\Http\JsonResponse;

return [
    /**
     * Enable/disable the pacakge
     */
    'enabled' => env('CDN_ENABLED', false),

    /**
     * Service classes
     *
     * Supported CDN services: Akamai, CloudFront
     *
     */
    'classes' => [
        'cdn' => A17\CDN\Services\Akamai\Service::class,

        'cache-control' => A17\CDN\Services\CacheControl::class,

        'tags' => A17\CDN\Services\Tags::class,
    ],

    /**
     * Caching strategies.
     *
     * Refer to https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     *
     * The default "do-not-cache" strategy takes in consideration that a 5 seconds cache
     * is better than NO-CACHE, and if your application gets hit by a DDoS attack
     * only 1 request every 5 seconds (per page) will hit your servers.
     *
     *  Warning: "cache" and "do-not-cache" strategies are built-in and used by the package.
     *           Do not delete them.
     *
     * These are the supported directives:
     *
     *   public
     *   private
     *   no-cache
     *   no-store
     *   max-age=<seconds>
     *   s-maxage=<seconds>
     *   max-stale=<seconds> // not supported yet
     *   min-fresh=<seconds> // not supported yet
     *   stale-while-revalidate=<seconds> // not supported yet
     *   stale-if-error=<seconds> // not supported yet
     *   must-revalidate
     *   proxy-revalidate
     *   immutable
     *   no-transform
     *   only-if-cached
     *
     */
    'strategies' => [
        'cache' => ['max-age', 'public'], // built-in

        'do-not-cache' => ['max-age=5', 'public'], // built-in

        'api' => ['max-age=20', 'public', 'no-store'], // custom
    ],

    /**
     * Define how max-age will work.
     *
     * default: in case max-age is not configured at runtime, and
     * pages are set to be cached, this is the default value.
     *
     * strategy: defines how the setter will behave:
     *    min = will use the minumum value set
     *    last = will use the last value set
     *
     */
    'max-age' => [
        'default' => 1 * Constants::WEEK,

        'strategy' => 'min', // min, last
    ],

    /**
     * In case max-age is not configured at runtime, and
     * pages are set to be cached, this is the default value.
     */
    'cache-control' => ['max-age' => 1 * Constants::WEEK],

    /**
     * We suppose only your frontend application needs to be
     * behind a CDN, please use this closure to tell the service
     * if the request was requested by the frontend or not.
     *
     * To allow everything you can just set
     *
     *    'frontend-checker' => fn() => true,
     *
     */
    'frontend-checker' => fn() => Str::startsWith(
        optional(request()->route())->getName(),
        ['front.', 'api.'],
    ),

    /**
     * List of cache control headers to add to responses
     */
    'headers' => [
        'cache-control' => ['Cache-Control', 'X-Cache-Control'],

        'tags' => ['Edge-Cache-Tag', 'X-Cache-Tag'],
    ],

    /**
     * Usually pages that contains forms should not be cached. Here you can
     * define if you want this to checked and what are valid form strings for
     * your application.
     */
    'valid_forms' => [
        'enabled' => true,

        'strings' => [
            '<form',
            '<input type="hidden" name="_token" value="%CSRF_TOKEN%"',
        ],
    ],

    /**
     * Allowed responses
     */
    'responses' => [
        'cachable' => [
            Illuminate\Http\Response::class,
            Illuminate\Http\JsonResponse::class,
        ],

        'not-cachable' => [],
    ],

    /**
     * Allowed routes. You can also tell the package if you want to cache nor not cache
     * routes without names.
     */
    'routes' => [
        'cachable' => [],

        'not-cachable' => ['*.ticket', 'newsletter*', 'api.*'],

        'cache_nameless_routes' => true,
    ],

    /**
     * Allowed methods
     */
    'methods' => [
        'cachable' => ['GET'],

        'not-cachable' => [],
    ],

    /**
     * Allowed response statuses
     */
    'statuses' => [
        'cachable' => [200, 301],

        'not-cachable' => [],
    ],

    /**
     * Tags configuration. Here you can exclude model classes and
     * define the format of the page-related tags.
     */
    'tags' => [
        'excluded-model-classes' => [
            '\Models\Translations*',
            '\Models\Slugs*',
            '\Models\Revisions*',
        ],

        'format' => 'app-%environment%-%sha1%',
    ],

    /**
     * Services configuration
     */
    'services' => [
        'akamai' => [
            'host' => env('CDN_AKAMAI_HOST'),
            'access_token' => env('CDN_AKAMAI_ACCESS_TOKEN'),
            'client_token' => env('CDN_AKAMAI_CLIENT_TOKEN'),
            'client_secret' => env('CDN_AKAMAI_CLIENT_SECRET'),
        ],

        'cloud_front' => [
            'sdk_version' => env('CDN_CLOUD_FRONT_SDK_VERSION', '2016-01-13'),
            'region' => env('CDN_AWS_DEFAULT_REGION', 'us-east-1'),
            'distribution_id' => env('CDN_AWS_CLOUDFRONT_DISTRIBUTION_ID'),
            'key' => env('CDN_AWS_CLOUDFRONT_KEY'),
            'secret' => env('CDN_AWS_CLOUDFRONT_SECRET'),
        ],
    ],
];
