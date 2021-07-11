<?php

namespace A17\CDN\Contracts;

use Symfony\Component\HttpFoundation\Response;

interface CDNService
{
    public function purge(array $items): void;
}
