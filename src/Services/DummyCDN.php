<?php

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Collection;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Contracts\CDNService;

class DummyCDN extends BaseService implements CDNService
{
    public function invalidate(Collection $items): bool
    {
        return true;
    }

    public function invalidateAll(): bool
    {
        return true;
    }
}
