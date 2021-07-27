<?php

namespace A17\EdgeFlush\Contracts;

use Illuminate\Http\Response;

interface Service
{
    public function makeResponse(Response $response): Response;
}
