<?php

use A17\EdgeFlush\Support\Constants;

return [
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
            'X-Edge-Flush-Warmed-Url',
            'X-Edge-Flush-Warmed-At',
        ],
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
            // Twill modules
            '\Models\Translations*',
            '\Models\Slugs*',
            '\Models\Revisions*',

            // Twill capsules
            '*\Models\*Translation',
            '*\Models\*Slug',
            '*\Models\*Revision',

            // Other classes
            'Spatie\Activitylog\Models\Activity',
        ],

        'format' => 'app-%environment%-%sha1%',
    ],
];
