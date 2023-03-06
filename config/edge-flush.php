<?php

use Illuminate\Support\Str;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Support\Constants;

return [
    /**
     * Enabled services
     */
    'enabled' => [
        'package' => env('EDGE_FLUSH_ENABLED', false),

        'services' => [
            'invalidation' => env('EDGE_FLUSH_INVALIDATION_SERVICE_ENABLED', false),

            'store-tags' => env('EDGE_FLUSH_STORE_TAGS_SERVICE_ENABLED', false),
        ],
    ],

    /**
     * Enable/disable the pacakge
     */
    'debug' => env('EDGE_FLUSH_DEBUG', false),
];
