<?php

namespace A17\EdgeFlush\Contracts;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

interface CDNService extends Service
{
    public function invalidate(Collection $items): bool;

    public function invalidateAll(): bool;
}
