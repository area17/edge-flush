<?php

namespace A17\CDN\Contracts;

use Symfony\Component\HttpFoundation\Response;

interface Service
{
    public function makeResponse(Response $response): Response;
}
