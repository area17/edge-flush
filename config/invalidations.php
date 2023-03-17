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
            'updated' => [
                /**
                 * Default behaviour if model not specified
                 *
                 * invalidate-dependents: invalidate paths that depends of this model and attribute
                 * invalidate-all: invalidate all paths
                 * invalidate-none: don't do anything
                 */
                'default' => 'invalidate-dependents', // invalidate-dependents, invalidate-all, invalidate-none

                /**
                 * We can setup specific behaviour for each model, depending on how their attributes changes
                 *
                 * Let's say you have a listing on your site that shows all the posts on a page when you publish them.
                 * If you create a new post unpublished, your pages should not be invalidated because this new post
                 * would not appear on a page. But if you publish it, your pages should be invalidated.
                 */
                'when-models' => [
                    [
                        /**
                         * List of models
                         */
                        'models' => [
                            'App\Models\Artwork',
                            'App\Models\Event',
                        ],

                        /**
                         * When a particular attribute changes
                         */
                        'on-change' => [
                            'published' => true, // attribute changed to "true"
                        ],

                        /**
                         * Invalidate the whole site
                         *
                         * TODO: should we tell which pages to invalidate here?
                         */
                        'strategy' => 'invalidate-all',
                    ]
                ]
            ],

            'created' => [
                'default' => 'invalidate-none',

                'when-models' => [
                    [
                        'models' => [
                            'App\Models\Person',
                        ],

                        'strategy' => 'invalidate-all',
                    ]
                ]
            ],

            'deleted' => [
                'default' => 'invalidate-all',
            ],
        ],
    ],
];
