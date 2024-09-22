<?php

return [
    /**
     * Allowed routes. You can also tell the package if you want to cache
     * routes that have no name.
     */
    'routes' => [
        'cachable' => ['*'],

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
];
