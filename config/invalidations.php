<?php

return [
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

        'crud-strategy' => [
            'update' => [
                'strategy' => 'invalidate-dependents', // invalidate-dependents, invalidate-all
            ],

            'create' => [
                'strategy' => 'invalidate-all',
            ]
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
            'enabled' => env('EDGE_FLUSH_CLOUD_FRONT_ENABLED', true),

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
];
