<?php

use A17\EdgeFlush\Support\Helpers;

return [
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
];
