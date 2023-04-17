<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services\Cdn;

use A17\EdgeFlush\Services\Invalidation;
use Symfony\Component\HttpFoundation\Response;
use A17\EdgeFlush\Behaviours\ControlsInvalidations;

/**
 * Let's just simulate a real CDN with this dummy
 */
class DummyCDN extends Base
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

    public function canInvalidateAll(): bool
    {
        return false;
    }

    public function isProperlyConfigured(): bool
    {
        return true;
    }

    public function makeResponse(Response $response): Response
    {
        return $response;
    }
}
