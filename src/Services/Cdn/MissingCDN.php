<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services\Cdn;

use A17\EdgeFlush\Services\Invalidation;
use Symfony\Component\HttpFoundation\Response;
use A17\EdgeFlush\Behaviours\ControlsInvalidations;

class MissingCDN extends Base
{
    use ControlsInvalidations;

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
