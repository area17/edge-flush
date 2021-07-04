<?php

namespace A17\CDN\Services;

use Symfony\Component\HttpFoundation\Response;

class BaseService
{
    public function addHttpHeadersToResponse(Response $respose): Response
    {
        return $respose;
    }
}
