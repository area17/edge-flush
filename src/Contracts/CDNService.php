<?php

namespace A17\EdgeFlush\Contracts;

use Illuminate\Support\Collection;
use A17\EdgeFlush\Services\Invalidation;
use Symfony\Component\HttpFoundation\Response;

interface CDNService extends Service
{
    public function invalidate(Collection $items): Invalidation;

    public function invalidateAll(): Invalidation;

    public function getInvalidationPathsForTags(Collection $tags): Collection;

    public function maxUrls(): int;

    public function invalidationIsCompleted(string $invalidationId): bool;
}
