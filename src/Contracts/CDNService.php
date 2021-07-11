<?php

namespace A17\CDN\Contracts;

use Symfony\Component\HttpFoundation\Response;

interface CDNService
{
    public function invalidate(array $items): void;
}
