<?php

use Illuminate\Http\Response;
use A17\CDN\Support\Constants;
use Illuminate\Http\JsonResponse;

return [
    'service' => A17\CDN\Services\Akamai\Service::class, // Akamai, CloudFront

    'cache-control' => ['max-age' => 1 * Constants::WEEK],

    'responses' => [
        'cachable' => [
            Illuminate\Http\Response::class,
            Illuminate\Http\JsonResponse::class,
        ],

        'not-cachable' => [],
    ],

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

    'methods' => [
        'cachable' => ['GET'],

        'not-cachable' => [],
    ],

    'statuses' => [
        'cachable' => [200, 301],

        'not-cachable' => [],
    ],
];
