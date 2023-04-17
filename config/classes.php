<?php

return [
    /**
     * Service classes
     *
     * Supported CDN services: Akamai, CloudFront
     *
     */
    'classes' => [
        'cdn' => A17\EdgeFlush\Services\Cdn\CloudFront::class,

        'cache-control' => A17\EdgeFlush\Services\CacheControl::class,

        'tags' => A17\EdgeFlush\Services\Tags::class,

        'warmer' => A17\EdgeFlush\Services\Warmer::class,
    ],
];
