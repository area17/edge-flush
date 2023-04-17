<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services\Cdn;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
use A17\EdgeFlush\Support\Helpers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use A17\EdgeFlush\Contracts\CDNService;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Services\Invalidation;

abstract class Base extends BaseService implements CDNService
{
    protected static string $serviceName = 'missing-service-name';

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

    public function serviceIsEnabled(): bool
    {
        return Helpers::configBool('edge-flush.services.'.static::$serviceName.'.enabled', true);
    }

    public function canInvalidateAll(): bool
    {
        return collect(
            Helpers::configString('edge-flush.services.'.static::$serviceName.'.invalidate_all_paths') ?? []
        )->filter()->isNotEmpty();
    }

    public function createInvalidationRequest(Invalidation|array|null $invalidation = []): Invalidation
    {
        if ($invalidation instanceof Invalidation) {
            return $invalidation;
        }

        if (is_null($invalidation)) {
            return new Invalidation();
        }

        $urls = [];
        $tags = [];
        $paths = [];

        foreach ($invalidation as $value) {
            if ($value instanceof Url) {
                $urls[] = $value;
            } else if ($value instanceof Tag) {
                $tags[] = $value;
            } else {
                $paths[] = $value;
            }
        }

        $result = new Invalidation();

        $result->setPaths(new Collection($paths));

        $result->setTags(new Collection($tags));

        $result->setUrls(new Collection($urls));

        return $result;
    }

    public function instantiate(): void
    {

    }
}
