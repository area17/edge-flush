<?php

namespace A17\EdgeFlush\Contracts;

use Illuminate\Support\Collection;
use A17\EdgeFlush\Services\Invalidation;
use Symfony\Component\HttpFoundation\Response;

interface CDNService extends Service
{
    public function invalidate(Invalidation $invalidation): Invalidation;

    public function invalidateAll(): Invalidation;

    public function getInvalidationPathsForTags(
        Invalidation $invalidation
    ): Collection;

    public function maxUrls(): int;

    public function invalidationIsCompleted(string $invalidationId): bool;
}
