<?php

return [
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
     * When a page is to be cached by the CDN, this package will strip the following
     * cookies from all responses.
     */
    'strip_cookies' => [
        'XSRF-TOKEN',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '*',
    ],
];
