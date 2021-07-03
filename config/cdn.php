<?php

use Illuminate\Support\Str;
use Illuminate\Http\Response;
use A17\CDN\Support\Constants;
use Illuminate\Http\JsonResponse;

return [
    /**
     * The CDN service currently caching pages. Possible options:
     *
     *    Akamai, CloudFront
     *
     */
    'cdn-service' => A17\CDN\Services\Akamai\Service::class,

    /**
     * The Cache Control service class.
     */
    'cache-control-service' => A17\CDN\Services\CacheControl::class,

    /**
     * List of possible resulting strategies
     *  must-revalidate
     *  no-cache
     *  no-store
     *  no-transform
     *  public
     *  private
     *  proxy-revalidate
     *  max-age=<seconds>
     *  s-maxage=<seconds>
     */
    'strategies' => ['do-not-cache' => 'no-store, private'],

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
     *    fn() => true
     *
     */
    'frontend-checker' => fn() => true,

    'frontend-checker-save' => fn() => Str::startsWith(
        optional(request()->route())->getName(),
        ['front.', 'api.'],
    ),

    /**
     * List of cache control headers to add to responses
     */
    'headers' => ['cache-control' => ['Cache-Control', 'X-Cache-Control']],

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
     * Allowed routes
     */
    'routes' => [
        'cachable' => [],

        'not-cachable' => [
            'pdf.ticket',
            'awallet.ticket',
            'gwallet.ticket',
            'newsletter.store',
            'newsletter',
            'api.',
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
];
