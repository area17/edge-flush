<?php

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
use A17\EdgeFlush\Jobs\StoreTags;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use A17\EdgeFlush\Support\Helpers;
use SebastianBergmann\Timer\Timer;
use A17\EdgeFlush\Support\Constants;
use A17\EdgeFlush\Jobs\InvalidateTags;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\ResponseCache\ResponseCache;
use Illuminate\Database\Events\QueryExecuted;
use Symfony\Component\HttpFoundation\Response;

class Tags
{
    protected array $tags = [];

    public $processedTags = [];

    public function addTag(Model $model): void
    {
        if (
            $this->wasNotProcessed($model) &&
            EdgeFlush::enabled() &&
            filled($tag = $this->makeTag($model))
        ) {
            $this->tags[$tag] = $tag;
        }
    }

    protected function deleteTags($tags, Invalidation $invalidation): void
    {
        $tags = is_string($tags)
            ? [$tags]
            : ($tags = collect($tags)
                ->map(function ($tag) {
                    if (is_string($tag)) {
                        return $tag;
                    }

                    return $tag->tag;
                })
                ->unique()
                ->toArray());

        if (blank($tags)) {
            return;
        }

        $tagList = $this->makeQueryItemsList($tags);

        $time = (string) now();

        $invalidationId = $invalidation->id();

        $this->markTagsAsObsolete(['type' => 'tag', 'items' => $tags]);

        $this->dbStatement("
            update edge_flush_urls efu
            set was_purged_at = '{$time}',
                invalidation_id = '{$invalidationId}'
            from (
                    select id
                    from edge_flush_urls efu
                    join edge_flush_tags eft on eft.url_id = efu.id
                    where efu.is_valid = true
                      and eft.tag in ({$tagList})
                    order by efu.id
                    for update
                ) urls
            where efu.id = urls.id
        ");
    }

    protected function getAllTagsForModel(
        string|null $modelString
    ): Collection|null {
        if (filled($modelString)) {
            return Tag::where('model', $modelString)->get();
        }

        return null;
    }

    public function getTags(): array
    {
        return collect($this->tags)
            ->reject(function (string $tag) {
                return $this->tagIsExcluded($tag);
            })
            ->values()
            ->toArray();
    }

    public function getTagsHash(Response $response, Request $request)
    {
        $tag = $this->makeEdgeTag($models = $this->getTags());

        if (
            EdgeFlush::cacheControl()->isCachable($response) &&
            EdgeFlush::storeTagsServiceIsEnabled()
        ) {
            StoreTags::dispatch(
                $models,
                [
                    'cdn' => $tag,

                    'response_cache' => EdgeFlush::responseCache()?->makeResponseCacheTag(
                        EdgeFlush::getRequest(),
                    ),
                ],
                $this->getCurrentUrl($request),
            );
        }

        return $tag;
    }

    public function makeEdgeTag($models = null)
    {
        $models ??= $this->getTags();

        $tag = str_replace(
            ['%environment%', '%sha1%'],
            [
                app()->environment(),
                sha1(
                    collect($models)
                        ->sort()
                        ->join(', '),
                ),
            ],
            config('edge-flush.tags.format'),
        );

        return $tag;
    }

    public function makeTag(Model $model): string|null
    {
        try {
            return method_exists($model, 'getCDNCacheTag')
                ? $model->getCDNCacheTag()
                : null;
        } catch (\Exception $exception) {
            return null;
        }
    }

    protected function tagIsExcluded(string $tag): bool
    {
        /**
         * @param callable(string $pattern): boolean $pattern
         */
        return collect(
            config('edge-flush.tags.excluded-model-classes'),
        )->contains(fn(string $pattern) => EdgeFlush::match($pattern, $tag));
    }

    protected function tagIsNotExcluded(string $tag): bool
    {
        return !$this->tagIsExcluded($tag);
    }

    public function storeCacheTags(
        array $models,
        array $tags,
        string $url
    ): void {
        if (
            !EdgeFlush::enabled() ||
            !EdgeFlush::storeTagsServiceIsEnabled() ||
            !$this->domainAllowed($url)
        ) {
            return;
        }

        Helpers::debug(
            'STORE-TAGS' .
                json_encode([
                    'models' => $models,
                    'tags' => $tags,
                    'url' => $url,
                ]),
        );

        DB::transaction(function () use ($models, $tags, $url) {
            $url = $this->createUrl($url);
            $now = (string) now();

            collect($models)->each(function (string $model) use (
                $tags,
                $url,
                $now
            ) {
                $index = $this->makeTagIndex($url, $tags, $model);

                $this->dbStatement("
                        insert into edge_flush_tags (index, url_id, tag, model, response_cache_hash, created_at, updated_at)
                        select '{$index}', {$url->id}, '{$tags['cdn']}', '{$model}', '{$tags['response_cache']}', '{$now}', '{$now}'
                        where not exists (
                            select 1
                            from edge_flush_tags
                            where index = '{$index}'
                        )
                        ");
            });
        }, 5);
    }

    public function dispatchInvalidationsForModel(
        array|string|Model $models
    ): void {
        if (blank($models)) {
            return;
        }

        $models = is_array($models) ? $models : [$models];

        Helpers::debug('INVALIDATING: CDN tags for models');

        InvalidateTags::dispatch([
            'type' => Constants::INVALIDATION_TYPE_MODEL,
            'items' => collect($models)
                ->map(
                    fn($model) => is_string($model)
                        ? $model
                        : $this->makeTag($model),
                )
                ->toArray(),
        ]);
    }

    public function invalidateTags(array|null $subject = null): void
    {
        if (!EdgeFlush::invalidationServiceIsEnabled()) {
            return;
        }

        if ($subject === null) {
            $this->invalidateObsoleteTags();

            return;
        }

        config('edge-flush.invalidations.type') === 'batch'
            ? $this->markTagsAsObsolete($subject)
            : $this->dispatchInvalidations($subject);
    }

    protected function invalidateObsoleteTags(): void
    {
        /**
         * Filter obsolete tags and related urls.
         * Making sure we invalidate the most busy pages first.
         */
        $query = Tag::select(
            'edge_flush_tags.tag',
            'edge_flush_urls.url as url_url',
            'edge_flush_urls.hits as url_hits',
            'edge_flush_urls.id as url_id',
        )
            ->join(
                'edge_flush_urls',
                'edge_flush_tags.url_id',
                '=',
                'edge_flush_urls.id',
            )
            ->groupBy(
                'edge_flush_tags.tag',
                'edge_flush_urls.id',
                'edge_flush_urls.url',
                'edge_flush_urls.hits',
            )
            ->whereRaw('edge_flush_tags.obsolete = true')
            ->whereRaw('edge_flush_urls.is_valid = true')
            ->orderBy('edge_flush_urls.hits', 'desc');

        /**
         * Let's first calculate the number of URLs we are invalidating.
         * If it's above max, just flush the whole website.
         */
        $limitToFlushRoot = config(
            'edge-flush.invalidations.batch.flush_roots_if_exceeds',
        );

        if ($this->getTotal($query) > $limitToFlushRoot) {
            $this->invalidateEntireCache();

            return;
        }

        /**
         * Get the actual list of paths that will be invalidated.
         * Never exceed the CDN max tags or urls that can be invalidated
         * at once.
         */
        $paths = EdgeFlush::cdn()->getInvalidationPathsForTags(
            $query->take($this->getMaxInvalidations())->get(),
        );

        /**
         * Let's dispatch invalidations only for what's configured.
         */
        $this->dispatchInvalidations([
            'type' => Constants::INVALIDATION_TYPE_PATH,
            'items' => $paths->toArray(),
        ]);
    }

    protected function markTagsAsObsolete(array $subject)
    {
        $items = $this->makeQueryItemsList($subject['items']);

        $this->dbStatement("
            update edge_flush_tags eft
            set obsolete = true
            from (
                    select id
                    from edge_flush_tags
                    where obsolete = false
                      and {$subject['type']} in ({$items})
                    order by id
                    for update
                ) tags
            where eft.id = tags.id
        ");
    }

    protected function dispatchInvalidations(array $subject): void
    {
        if (blank($subject)) {
            return;
        }

        $paths = $this->getInvalidationPaths($subject);

        if (blank($paths)) {
            return;
        }

        EdgeFlush::responseCache()?->invalidate($subject);

        $invalidation = EdgeFlush::cdn()->invalidate(collect($paths));

        if ($invalidation->success()) {
            // TODO: what happens here on Akamai?
            $this->deleteTags($paths, $invalidation);
        }
    }

    protected function invalidateEntireCache()
    {
        Helpers::debug('INVALIDATING: entire cache...');

        EdgeFlush::cdn()->invalidate(
            collect(config('edge-flush.invalidations.batch.roots')),
        );
    }

    /*
     * Optimized for speed, 2000 calls to EdgeFlush::tags()->addTag($model) are now only 8ms
     */
    protected function wasNotProcessed(Model $model): bool
    {
        $id = $model->getAttributes()[$model->getKeyName()] ?? null;

        if ($id === null) {
            return false; /// don't process models with no ID yet
        }

        $key = $model->getTable() . '-' . $id;

        if ($this->processedTags[$key] ?? false) {
            return false;
        }

        $this->processedTags[$key] = true;

        return true;
    }

    public function invalidateAll(): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        $count = 0;

        do {
            if ($count++ > 0) {
                sleep(2);
            }

            $success = EdgeFlush::cdn()->invalidateAll();
        } while ($count < 3 && !$success);

        if (!$success) {
            return false;
        }

        EdgeFlush::responseCache()?->invalidateAll();

        $this->deleteAllTags();

        return true;
    }

    public function getCurrentUrl($request)
    {
        return $request->header('X-Edge-Flush-Warmed-Url') ?? url()->full();
    }

    protected function deleteAllTags(): void
    {
        // Purge all tags
        $this->dbStatement("
            update edge_flush_tags eft
            set obsolete = true
            from (
                    select id
                    from edge_flush_tags
                    where obsolete = false
                    order by id
                    for update
                ) tags
            where eft.id = tags.id
        ");

        // Purge all urls
        $now = (string) now();

        $this->dbStatement("
            update edge_flush_urls efu
            set was_purged_at = $now
            from (
                    select id
                    from edge_flush_urls
                    where is_valid = true
                    order by id
                    for update
                ) urls
            where efu.id = urls.id
        ");
    }

    public function domainAllowed($url): bool
    {
        $allowed = collect(config('edge-flush.domains.allowed'))->filter();

        $blocked = collect(config('edge-flush.domains.blocked'))->filter();

        if ($allowed->isEmpty() && $blocked->isEmpty()) {
            return true;
        }

        $domain = Helpers::parseUrl($url)['host'];

        return $allowed->contains($domain) && !$blocked->contains($domain);
    }

    private function getTotal($query): int
    {
        return DB::select(
            DB::raw("select count(*) from ({$query->toSql()}) count"),
        )[0]->count ?? 0;
    }

    public function getInvalidationPaths(array $subject): array|null
    {
        if ($subject['type'] === Constants::INVALIDATION_TYPE_TAG) {
            return $this->getPathsForTags($subject['items']);
        } elseif ($subject['type'] === Constants::INVALIDATION_TYPE_MODEL) {
            return $this->getPathsForModels($subject['items']);
        } elseif ($subject['type'] === Constants::INVALIDATION_TYPE_PATH) {
            return $subject['items'];
        }

        return null;
    }

    public function getPathsFor(array $items, string $type)
    {
        return EdgeFlush::cdn()
            ->getInvalidationPathsForTags(
                Tag::whereIn($type, $items)
                    ->take($this->getMaxInvalidations())
                    ->get(),
            )
            ->toArray();
    }

    public function getPathsForTags(array $tags): array
    {
        return $this->getPathsFor($tags, 'tag');
    }

    public function getPathsForModels(array $models): array
    {
        return $this->getPathsFor($models, 'model');
    }

    public function getMaxInvalidations(): int
    {
        return min(
            EdgeFlush::cdn()->maxUrls(),
            config('edge-flush.invalidations.batch.size'),
        );
    }

    public function dbStatement($query)
    {
        DB::statement(DB::raw($query));
    }

    /**
     * @param $items1
     * @return string
     */
    public function makeQueryItemsList($items1): string
    {
        return collect((array) $items1)
            ->map(fn($model) => "'$model'")
            ->join(',');
    }

    public function enabled(): bool
    {
        return EdgeFlush::invalidationServiceIsEnabled();
    }

    /**
     * @param string $url
     * @return mixed
     */
    function createUrl(string $url)
    {
        $url = Helpers::sanitizeUrl($url);

        $url = Url::firstOrCreate(
            ['url_hash' => sha1($url)],
            [
                'url' => Str::limit($url, 255),
                'hits' => 1,
            ],
        );

        if (!$url->wasRecentlyCreated) {
            $url->incrementHits();
        }

        return $url;
    }

    public function makeTagIndex($url, $tags, $model)
    {
        $index = "{$url->id}-{$tags['cdn']}-{$model}-{$tags['response_cache']}";

        return sha1($index);
    }
}
