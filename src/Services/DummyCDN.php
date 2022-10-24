<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\Contracts\CDNService;

class DummyCDN extends BaseService implements CDNService
{
    public function invalidate(Invalidation $invalidation): Invalidation
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

    public function invalidationIsCompleted(string $invalidationId): bool
    {
        return false;
    }
}
