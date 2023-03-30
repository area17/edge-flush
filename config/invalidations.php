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

        /**
         * Ignore certain attributes when invalidating or creating tags
         */
        'attributes' => [
            'ignore' => [
                '*' => ['id', 'locale', 'localMacros', 'without'],

                'App\Models\Translations\EventTranslation' => ['event_id']
            ],
            'always-add' => [
                '*' => ['published']
            ],
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
                    ],
                    [
                        'models' => [
                            A17\EdgeFlush\Models\URL::class,
                            A17\EdgeFlush\Models\Tag::class,
                        ],

                        'strategy' => 'invalidate-none',
                    ]
                ]
            ],

            'created' => [
                /**
                 * If when-model condition is not met, we don't do anything
                 */
                'default' => 'invalidate-none',

                'when-models' => [
                    [
                        'models' => [
                            'App\Models\Person',
                        ],

                        /**
                         * If we are creating a new person, already published, we should invalidate the whole site
                         */
                        'on-change' => [
                            'published' => true, // attribute changed to "true"
                        ],

                        'strategy' => 'invalidate-all',
                    ],
                    [
                        'models' => [
                            A17\EdgeFlush\Models\Url::class,
                            A17\EdgeFlush\Models\Tag::class,
                        ],

                        'strategy' => 'invalidate-none',
                    ]
                ],
            ],

            'deleted' => [
                /**
                 * Anything deleted on the website invalidates the whole site
                 */
                'default' => 'invalidate-all',

                'when-models' => [
                    [
                        'models' => [
                            A17\EdgeFlush\Models\Url::class,
                            A17\EdgeFlush\Models\Tag::class,
                        ],

                        'strategy' => 'invalidate-none',
                    ],
                ],
            ],

            'pivot-synced' => [
                /**
                 * Anything deleted on the website invalidates the whole site
                 */
                'default' => 'invalidate-dependents',
            ],
        ],
    ],
];
