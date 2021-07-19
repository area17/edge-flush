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
     * Configure here the default strategies used internally.
     * You can still manually set the current strategy at run-time.
     */
    'built-in-strategies' => [
        'cache' => 'dynamic',

        'dont-cache' => 'micro',
    ],

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

        'warmer' => A17\CDN\Services\Warmer::class,
    ],

    /**
     * Caching strategies.
     *
     * Refer to https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     *
     * The default "micro-cache" strategy takes in consideration that a 5 seconds cache
     * is better than NO-CACHE, and if your application gets hit by a DDoS attack
     * only 1 request every 5 seconds (per page) will hit your servers.

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
        'dynamic' => ['max-age', 'public'], // built-in

        'micro' => ['max-age=5', 'public'], // built-in

        'zero' => ['max-age=0', 'no-cache'], // custom

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
     *    'frontend-checker' => true,
     *
     */
    'frontend-checker' => A17\CDN\Services\FrontendChecker::class,

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
     * Allowed routes. You can also tell the package if you want to cache
     * routes that have no name.
     */
    'routes' => [
        'cachable' => [],

        'not-cachable' => ['*.ticket', 'newsletter*', 'api.*'],

        'cache_nameless_routes' => true,
    ],

    /**
     * Allowed URLs.
     */
    'urls' => [
        'cachable' => ['**/**'],

        'not-cachable' => ['**/debug/**'],
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
     * Invalidations.
     *
     * Invalidations can be sent one by one or in batch. In this section you can configure
     * this and also set the limits.
     *
     * single invalidations are executed immediately after a model is updated.
     * batch invalidations are executed every x minutes.
     *
     * batch.max_paths: what's is the limit before we invalidate the whole site?
     * batch.site_root: what's the site root path that should be invalidated?
     */
    'invalidations' => [
        'method' => 'invalidate', // invalidate, delete

        'type' => 'single', // single, batch

        'batch' => [
            'max_tags' => 2999, /// CloudFront limit is 3000
            'site_roots' => ['/*'],
        ],
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
            'invalidate_all_paths' => ['*'],
        ],

        'cloud_front' => [
            'sdk_version' => env('CDN_CLOUD_FRONT_SDK_VERSION', '2016-01-13'),

            'region' => env(
                'CDN_AWS_DEFAULT_REGION',
                env('AWS_DEFAULT_REGION', 'us-east-1'),
            ),

            'distribution_id' => env('CDN_AWS_CLOUDFRONT_DISTRIBUTION_ID'),

            'key' => env('CDN_AWS_CLOUDFRONT_KEY', env('AWS_ACCESS_KEY_ID')),

            'secret' => env(
                'CDN_AWS_CLOUDFRONT_SECRET',
                env('AWS_SECRET_ACCESS_KEY'),
            ),

            'invalidate_all_paths' => ['/*'],
        ],
    ],

    /**
     * Purged cache can be rewarmed. Enable and configure it here.
     *
     * max_urls: how many urls max should the warmer try to warm per session?
     *
     * max_time: how much time the whole job warming session can take?
     *
     * connection_timeout: to warm up a page we don't actually need to get the page contents
     *                     that's why the warmer will kill the connection (possibly) before
     *                     the server is able to respond with contents. The idea here is
     *                     to speed up the warming process and save bandwidth.
     *
     * concurrent_requests: how many concurrent requests the warmer should dispatch per session?
     *
     * warm_all_on_purge: if the whole CDN cache is purged, do you wish to warm back all pages?
     *
     * wait_before_warming: invalidating tags can take time, maybe it's good to wait a couple
     *                      of minutes before warming purged urls.
     *
     */
    'warmer' => [
        'enabled' => env('CDN_WARMER_ENABLED', false),

        'max_urls' => 100,

        'max_time' => Constants::MILLISECOND * 750,

        'connection_timeout' => Constants::MILLISECOND * 15,

        'concurrent_requests' => 50,

        'warm_all_on_purge' => true,

        'wait_before_warming' => Constants::MINUTE * 2,
    ],

    /**
     * When a page is to be cached by the CDN this package will strip the following
     * cookies from all responses.
     */
    'strip_cookies' => [
        'XSRF-TOKEN',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '*',
    ],
];
