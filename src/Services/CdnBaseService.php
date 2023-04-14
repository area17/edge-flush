<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use A17\EdgeFlush\Contracts\CDNService;

abstract class CdnBaseService extends BaseService implements CDNService
{
    public function __construct()
    {
        if ($this->enabled()) {
            $this->instantiate();
        }
    }

    public function invalidate(Invalidation $invalidation): Invalidation
    {
        if (!$this->enabled()) {
            return $invalidation;
        }

        return $this->mustInvalidateAll($invalidation) ? $this->invalidateAll() : $this->invalidatePaths($invalidation);
    }

    public function invalidateAll(): Invalidation
    {
        if (!$this->enabled()) {
            return $this->unsuccessfulInvalidation();
        }

        return $this->createInvalidationRequest(
            Helpers::configArray('edge-flush.services.'.static::$serviceName.'.invalidate_all_paths'),
        );
    }

    protected function getInvalidationPath(mixed $item): string|null
    {
        if (is_string($item)) {
            return $item;
        }

        $url = Helpers::getUrl($item);

        if ($url === null) {
            return null;
        }

        return Helpers::parseUrl($url)['path'] ?? '/*';
    }

    public function getInvalidationPathsForTags(Invalidation $invalidation): Collection
    {
        if ($invalidation->paths()->isEmpty()) {
            $paths = (new Collection($invalidation->tags()))
                ->mapWithKeys(fn($tag) => [$this->getInvalidationPath($tag) => $tag])
                ->keys()
                ->unique()
                ->take($this->maxUrls());

            $invalidation->setPaths($paths);
        }

        return $invalidation->paths();
    }

    public function mustInvalidateAll(Invalidation $invalidation): bool
    {
        if (!$this->canInvalidateAll()) {
            return false;
        }

        return $this->getInvalidationPathsForTags($invalidation)->count() >= $this->maxUrls();
    }

    public function invalidatePaths(Invalidation $invalidation): Invalidation
    {
        return $this->createInvalidationRequest($invalidation);
    }

    public function maxUrls(): int
    {
        return Helpers::configInt('edge-flush.services.'.static::$serviceName.'.max_urls') ?? 300;
    }

    public function enabled(): bool
    {
        return EdgeFlush::enabled() &&
            $this->serviceIsEnabled() &&
            $this->isProperlyConfigured();
    }

    public function invalidationIsCompleted(string $invalidationId): bool
    {
        $response = $this->getInvalidation($invalidationId);

        if (blank($response)) {
            return false;
        }

        return Invalidation::factory($response)->isCompleted();
    }

    public function getInvalidation(string $invalidationId): AwsResult
    {
        return $this->client->getInvalidation([
            'DistributionId' => $this->getDistributionId(),
            'Id' => $invalidationId,
        ]);
    }

    public function serviceIsEnabled(): bool
    {
        return Helpers::configBool('edge-flush.services.'.static::$serviceName.'.enabled', true);
    }

    public function getMaxUrls(): int
    {
        return Helpers::configInt('edge-flush.services.'.static::$serviceName.'.max_urls', 300);
    }

    public function canInvalidateAll(): bool
    {
        return collect(
            Helpers::configString('edge-flush.services.'.static::$serviceName.'.invalidate_all_paths') ?? []
        )->filter()->isNotEmpty();
    }
}
