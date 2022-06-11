<?php

namespace A17\EdgeFlush\Services;

use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Tag;
use A17\EdgeFlush\Models\Url;
use A17\EdgeFlush\Jobs\StoreTags;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Jobs\InvalidateTags;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\ResponseCache\ResponseCache;
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

    protected function deleteTags($tags): void
    {
        $tags = is_string($tags)
            ? [$tags]
            : ($tags = $tags
                ->pluck('tag')
                ->unique()
                ->toArray());

        Tag::whereIn('tag', $tags)->update([
            'obsolete' => false,
        ]);

        Url::join(
            'edge_flush_tags',
            'edge_flush_tags.url_id',
            '=',
            'edge_flush_urls.id',
        )
            ->whereIn('edge_flush_tags.tag', $tags)
            ->update([
                'was_purged_at' => now(),
            ]);
    }

    protected function getAllTagsForModel(?string $modelString)
    {
        if (filled($modelString)) {
            return Tag::where('model', $modelString)->get();
        }
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

        if (EdgeFlush::cacheControl()->isCachable($response)) {
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
        if (!EdgeFlush::enabled() || !$this->domainAllowed($url)) {
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

        DB::transaction(
            fn() => collect($models)->each(function (string $model) use (
                $tags,
                $url
            ) {
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

                Tag::firstOrCreate([
                    'model' => $model,
                    'tag' => $tags['cdn'],
                    'response_cache_hash' => $tags['response_cache'],
                    'url_id' => $url->id,
                ]);
            }),
            5,
        );
    }

    public function dispatchInvalidationsForModel(Model $model): void
    {
        InvalidateTags::dispatch($model);
    }

    public function invalidateTagsForModel($model): void
    {
        $tags = $this->getAllTagsForModel($this->makeTag($model))
            ->pluck('tag')
            ->unique()
            ->filter();

        if ($tags->isEmpty()) {
            return;
        }

        Helpers::debug(
            'INVALIDATING: CDN tags for model ' .
                $this->makeTag($model) .
                '. Found: ' .
                count($tags ?? []) .
                ' tags',
        );

        Helpers::debug('TAGS: ' . json_encode($tags->values()->toArray()));

        $this->invalidateTags($tags);
    }

    public function invalidateTags(mixed $tags = null): void
    {
        if (is_null($tags)) {
            $this->invalidateObsoleteTags();

            return;
        }

        if (config('edge-flush.invalidations.type') === 'batch') {
            $this->markTagsAsObsolete($tags);

            return;
        }

        $this->dispatchInvalidations(
            Tag::whereIn('tag', $tags->toArray())->get(),
        );
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
            ->where('edge_flush_tags.obsolete', true)
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
            $query
                ->take(
                    min(
                        EdgeFlush::cdn()->maxUrls(),
                        config('edge-flush.invalidations.batch.size'),
                    ),
                )
                ->get(),
        );

        /**
         * Let's dispatch invalidations only for what's configured.
         */
        $this->dispatchInvalidations($paths);
    }

    protected function markTagsAsObsolete(Collection $tags): void
    {
        Tag::whereIn('tag', $tags->toArray())->update(['obsolete' => true]);
    }

    protected function dispatchInvalidations(Collection $paths): void
    {
        if ($paths->isEmpty()) {
            return;
        }

        EdgeFlush::responseCache()?->invalidate($paths);

        if (EdgeFlush::cdn()->invalidate($paths)) {
            // TODO: what happens here on Akamai?
            $this->deleteTags($paths);
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
        if (!EdgeFlush::enabled()) {
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

        EdgeFlush::responseCache()->invalidateAll();

        $this->deleteAllTags();

        return true;
    }

    public function getCurrentUrl($request)
    {
        return $request->header('X-EDGE-FLUSH-WARMING-URL') ?? url()->full();
    }

    protected function deleteAllTags(): void
    {
        Tag::whereNotNull('id')->update([
            'obsolete' => true,
        ]);

        Url::whereNotNull('id')->update([
            'was_purged_at' => now(),
        ]);
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
            [true], // edge_flush_tags.obsolete = true
        )[0]->count ?? 0;
    }
}
