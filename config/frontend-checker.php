<?php

return [
    /**
     * We suppose only your frontend application needs to be
     * behind a CDN, please use this closure to tell the service
     * if the request was requested by the frontend or not.
     *
     * To allow everything you can just set
     *
     *    'frontend-checker' => true,
     *
     */
    'frontend-checker' => A17\EdgeFlush\Services\FrontendChecker::class,
];
