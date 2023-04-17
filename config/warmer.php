<?php

return [
    /**
     * Purged cache can be rewarmed. Enable and configure it here.
     *
     * max_urls: how many urls max should the warmer try to warm per session?
     *
     * max_time: how much time the whole job warming session can take?
     *
     * connection_timeout: to warm up a page we don't actually need to get the page contents
     *                     that's why the warmer will kill the connection (possibly) before
     *                     the server is able to respond with contents. The idea here is
     *                     to speed up the warming process and save bandwidth.
     *
     * concurrent_requests: how many concurrent requests the warmer should dispatch per session?
     *
     * warm_all_on_purge: if the whole CDN cache is purged, do you wish to warm back all pages?
     *
     * basic_authentication: to be used by Guzzle to authenticate
     *
     * headers: as the headers are not built by a webserver, we need to pass the actual
     * expected headers after webserver or PHP processed them. It's the case for the
     * Authorization, which, after unpacking, is translated to PHP_AUTH_USER and PHP_AUTH_PW
     */
    'warmer' => [
        'enabled' => env('EDGE_FLUSH_WARMER_ENABLED', false),

        'types' => ['internal', 'external'],

        'max_urls' => 100,

        'max_time' => \A17\EdgeFlush\Support\Constants::MILLISECOND * 1000,

        'connection_timeout' => \A17\EdgeFlush\Support\Constants::MILLISECOND * 500,

        'concurrent_requests' => 50,

        'warm_all_on_purge' => true,

        'basic_authentication' => [
            'username' => ($username = env('HTTP_AUTH_USER')),
            'password' => ($password = env('HTTP_AUTH_PASSWORD'))
        ],

        'headers' => [
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => $password,
        ],

        'check_ssl_certificate' => false, // It's too slow to check SSL certificates

        'curl' => [
            'connect_only' => false, // only connect to the server?

            'get_body' => true, // get the page data? Use HEAD instead of GET

            'compress' => true, // force cURL requests to behave like browser ones by accepting compressed content

            'extra_options' => [] // cURL extra options
        ],

        'extra_options' => [], // Guzzle extra options
    ],
];
