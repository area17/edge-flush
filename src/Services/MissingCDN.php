<?php

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\Contracts\CDNService;

class MissingCDN extends BaseService implements CDNService
{
    protected bool|null $enabled = false;

    public function invalidate(Invalidation $invalidation): Invalidation
    {
        return $this->unsuccessfulInvalidation();
    }

    public function invalidateAll(): Invalidation
    {
        return $this->unsuccessfulInvalidation();
    }

    public function maxUrls(): int
    {
        return 0;
    }

    public function invalidationIsCompleted(string $invalidationId): bool
    {
        return false;
    }
}
