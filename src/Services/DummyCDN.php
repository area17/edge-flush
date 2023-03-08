<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\Contracts\CDNService;

/**
 * Let's just simulate a real CDN with this dummy
 */
class DummyCDN extends BaseService implements CDNService
{
    public function invalidate(Invalidation $invalidation): Invalidation
    {
        return $invalidation->setSuccess(true);
    }

    public function invalidateAll(): Invalidation
    {
        return $this->successfulInvalidation();
    }

    public function maxUrls(): int
    {
        return 500;
    }

    public function invalidationIsCompleted(string $invalidationId): bool
    {
        return true;
    }
}
