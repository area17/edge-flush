<?php

return [
    /**
     * Services configuration
     */
    'services' => [
        'akamai' => [
            'host' => env('EDGE_FLUSH_AKAMAI_HOST', env('AKAMAI_HOST')),

            'access_token' => env('EDGE_FLUSH_AKAMAI_ACCESS_TOKEN', env('AKAMAI_ACCESS_TOKEN')),

            'client_token' => env('EDGE_FLUSH_AKAMAI_CLIENT_TOKEN', env('AKAMAI_CLIENT_TOKEN')),

            'client_secret' => env('EDGE_FLUSH_AKAMAI_CLIENT_SECRET', env('AKAMAI_CLIENT_SECRET')),

            'invalidate_all_paths' => null, // there's no invalidate all on Akamai

            'max_urls' => 499, // Akamai is limited to 500 cache tags per minute
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
