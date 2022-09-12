<?php

namespace A17\EdgeFlush\Services;

use Illuminate\Support\Collection;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Contracts\CDNService;
use A17\EdgeFlush\Services\Invalidation;

class DummyCDN extends BaseService implements CDNService
{
    public function invalidate(Collection $items): Invalidation
    {
        return $this->successfulInvalidation();
    }

    public function invalidateAll(): Invalidation
    {
        return $this->successfulInvalidation();
    }

    public function maxUrls(): int
    {
        return 0;
    }

    public function invalidationIsCompleted($invalidationId): bool
    {
        return false;
    }
}
