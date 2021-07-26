<?php

namespace A17\EdgeFlush\Contracts;

use Symfony\Component\HttpFoundation\Response;

interface Service
{
    public function makeResponse(Response $response): Response;
}
