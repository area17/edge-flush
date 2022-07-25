<?php

use Illuminate\Support\Str;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Support\Constants;

return [
    /**
     * Enable/disable the package
     */
    'enabled' => env('EDGE_FLUSH_ENABLED', false),

    /**
     * Only allowed domains will have tags stored.
     * An empty array will allow all domains.
     */
    'domains' => [
        'allowed' => [
            Helpers::parseUrl(env('APP_URL'))['host']
        ],

        'blocked' => []
    ],

    /**
     * Enable/disable the pacakge
     */
    'debug' => env('EDGE_FLUSH_DEBUG', false),

    /**
     * Set here the default strategy used when a strategy was not set
     * dynamically at runtime.
     */
    'default-strategies' => [
        'cachable-requests' => 'dynamic-cache',
        'non-cachable-requests' => 'micro-cache',
        'non-cachable-http-methods' => 'zero-cache',
        'pages-with-valid-forms' => 'zero-cache',
    ],

    /**
     * Configure here the default strategies used internally.
     * You can still manually set the current strategy at run-time.
     */
    'built-in-strategies' => [
        'dynamic-cache' => 'dynamic',

        'zero-cache' => 'zero',

        'micro-cache' => 'micro',

        'short-cache' => 'short',

        'long-cache' => 'long',

        'max-cache' => 'max',
    ],

    /**
     * Service classes
     *
     * Supported CDN services: Akamai, CloudFront
     *
     */
    'classes' => [
        'cdn' => A17\EdgeFlush\Services\CloudFront\Service::class,

        'cache-control' => A17\EdgeFlush\Services\CacheControl::class,

        'tags' => A17\EdgeFlush\Services\Tags::class,

        'warmer' => A17\EdgeFlush\Services\Warmer::class,

        // 'response-cache' => A17\EdgeFlush\Services\ResponseCache\Service::class,
    ],

    /**
     * Caching strategies.
     *
     * Refer to https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control
     *
     * The default "micro-cache" strategy takes in consideration that a 5 seconds cache
     * is better than NO-CACHE, and if your application gets hit by a DDoS attack
     * only 1 request every 5 seconds (per page) will hit your servers.
     *
     * The "dynamic" strategy will compute the max-age for objects according to the
     * calls to CacheControl::maxAge() and CacheControl::sMaxAge(). The max-age value will
     * depend on the max-age strategy, the last one set for "last" or the minimum one for "min".
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
        'dynamic' => ['s-maxage', 'max-age', 'public'], // built-in

        'zero' => ['s-maxage=0', 'max-age=0', 'no-store'], // built-in

        'micro' => ['s-maxage=' . 5 * Constants::SECOND, 'max-age=0', 'public'], // built-in // default is at least to cache a page for 5 seconds

        'short' => ['s-maxage=' . 2 * Constants::MINUTE, 'max-age=0', 'public'], // built-in

        'long' => ['s-maxage=' . 7 * Constants::DAY, 'max-age=0', 'public'], // built-in

        'max' => ['s-maxage=' . 12 * Constants::MONTH, 'max-age=0', 'public'], // built-in

        'api' => [
            's-maxage=' . 20 * Constants::SECOND,
            'max-age=0',
            'public',
            'no-store',
        ], // custom
    ],

    /**
     * Define how s-maxage (CDN cache) and max-age (browser cache) will work.
     *
     * default: in case they are not configured at runtime, and
     * pages are set to be cached, this is the default value.
     *
     * strategy: defines how the setter will behave:
     *    min = will use the minimum value set
     *    last = will use the last value set
     */
    'max-age' => [
        'default' => 0,

        'strategy' => 'min', // min, last
    ],

    's-maxage' => [
        'default' => /* one */ Constants::WEEK,

        'strategy' => 'min', // min, last
    ],

    /**
     * In case s-maxage and max-age are not configured at runtime, and
     * pages are set to be cached, this is the default value.
     */
    'cache-control' => ['s-maxage' => /* one */ Constants::WEEK, 'max-age' => 0],

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
    'frontend-checker' => A17\EdgeFlush\Services\FrontendChecker::class,

    /**
     * List of cache control headers to add to responses
     */
    'headers' => [
        'cache-control' => [
            'Cache-Control',
            'X-Edge-Flush-Cache-Control'
        ],

        'tags' => [
            'Edge-Cache-Tag',
            'X-Edge-Flush-Cache-Tag'
        ],

        'from-request' => [
            'X-Edge-Flush-Warming-Url',
            'X-Edge-Flush-Warmed-At',
        ],
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
     *
     * The 'query' configuration defines which query parameters should be retained
     * for URL caching, on which routes and/or URL paths
     */
    'urls' => [
        'cachable' => ['**/**'],

        'not-cachable' => ['**/debug/**'],

        'query' => [
            'fully_cachable' => false,

            'allow_routes' => [
                '/search' => ['q'],              // URL path
                'front.global.search' => ['q']   // Route name
            ]
        ]
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
     * method:
     *   invalidate: tell CDN to invalidate the page
     *   delete: tell CDN to delete the page
     *
     * type:
     *   single: invalidations are executed immediately after a model is updated.
     *   batch: invalidations are executed every x minutes.
     *
     * batch:
     *   size: How many invalidations should be sent every time we invalidate?
     *   flush_roots_if_exceeds: If there are more than this, we should just invalidate the whole site
     *   roots: What are the paths to invalidate "the whole site"?
     */
    'invalidations' => [
        'method' => 'invalidate', // invalidate, delete

        'type' => 'batch', // single, batch

        'batch' => [
            'size' => 2999, /// urls

            'flush_roots_if_exceeds' => 15000,

            'roots' => ['/*'],
        ],
    ],

    /**
     * Services configuration
     */
    'services' => [
        'akamai' => [
            'host' => env('EDGE_FLUSH_AKAMAI_HOST'),
            'access_token' => env('EDGE_FLUSH_AKAMAI_ACCESS_TOKEN'),
            'client_token' => env('EDGE_FLUSH_AKAMAI_CLIENT_TOKEN'),
            'client_secret' => env('EDGE_FLUSH_AKAMAI_CLIENT_SECRET'),
            'invalidate_all_paths' => ['*'],
            'max_urls' => 500, // Akamai is limited to 500 cache tags per minute
        ],

        'cloud_front' => [
            'sdk_version' => env(
                'EDGE_FLUSH_CLOUD_FRONT_SDK_VERSION',
                '2016-01-13',
            ),

            'region' => env(
                'EDGE_FLUSH_AWS_DEFAULT_REGION',
                env('AWS_DEFAULT_REGION', 'us-east-1'),
            ),

            'distribution_id' => env(
                'EDGE_FLUSH_AWS_CLOUDFRONT_DISTRIBUTION_ID',
            ),

            'key' => env(
                'EDGE_FLUSH_AWS_CLOUDFRONT_KEY',
                env('AWS_ACCESS_KEY_ID'),
            ),

            'secret' => env(
                'EDGE_FLUSH_AWS_CLOUDFRONT_SECRET',
                env('AWS_SECRET_ACCESS_KEY'),
            ),

            'invalidate_all_paths' => ['/*'],

            'max_urls' => 3000, // CloudFront has this limit
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
     * basic_authentication: to be used by Guzzle to authenticate
     *
     * headers: as the headers are not built by a webserver, we need to pass the actual
     * expected headers after webserver or PHP processed them. It's the case for the
     * Authorization, which, after unpacking, is translated to PHP_AUTH_USER and PHP_AUTH_PW
     */
    'warmer' => [
        'enabled' => env('EDGE_FLUSH_WARMER_ENABLED', false),

        'types' => ['internal', 'external'],

        'max_urls' => 100,

        'max_time' => Constants::MILLISECOND * 1000,

        'connection_timeout' => Constants::MILLISECOND * 500,

        'concurrent_requests' => 50,

        'warm_all_on_purge' => true,

        'basic_authentication' => [
            'username' => ($username = env('HTTP_AUTH_USER')),
            'password' => ($password = env('HTTP_AUTH_PASSWORD'))
        ],

        'headers' => [
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => $password,
        ],

        'check_ssl_certificate' => false, // It's too slow to check SSL certificates

        'curl' => [
            'connect_only' => false, // only connect to the server?

            'get_body' => true, // get the page data? Use HEAD instead of GET

            'compress' => true, // force cURL requests to behave like browser ones by accepting compressed content

            'extra_options' => [] // cURL extra options
        ],

        'extra_options' => [], // Guzzle extra options
    ],

    /**
     * When a page is to be cached by the CDN, this package will strip the following
     * cookies from all responses.
     */
    'strip_cookies' => [
        'XSRF-TOKEN',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '*',
    ],
];
