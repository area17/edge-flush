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
            : ($tags = $tags->pluck('tag')->toArray());

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

                    'response_cache' => EdgeFlush::responseCache()->makeResponseCacheTag(
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
            [app()->environment(), sha1(collect($models)->join(', '))],
            config('edge-flush.tags.format'),
        );

        return $tag;
    }

    public function makeTag(Model $model): ?string
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
                    $url->hits++;

                    $url->save();
                }

                Tag::firstOrCreate([
                    'model' => $model,
                    'tag' => $tags['cdn'],
                    'response_cache_hash' => $tags['response_cache'],
                    'url_id' => $url->id,
                ]);
            }), 5
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
            ->toArray();

        if (blank($tags)) {
            return;
        }

        $this->invalidateTags($tags);
    }

    public function invalidateTags($tags = null): void
    {
        if (blank($tags)) {
            $this->invalidateObsoleteTags();

            return;
        }

        if (config('edge-flush.invalidations.type') === 'batch') {
            $this->markTagsAsObsolete($tags);

            return;
        }

        $this->dispatchInvalidations(Tag::whereIn('tag', $tags)->get());
    }

    protected function invalidateObsoleteTags(): void
    {
        $max = config('edge-flush.invalidations.batch.flush_roots_if_exceeds');

        /**
         * Try to limit a bit the number of records we are reaching
         * Invalidate the most accessed pages first
         */
        $tags = Tag::select('edge_flush_tags.*')
            ->where('edge_flush_tags.obsolete', true)
            ->join(
                'edge_flush_urls',
                'edge_flush_tags.url_id',
                '=',
                'edge_flush_urls.id',
            )
            ->orderBy('edge_flush_urls.hits', 'desc')
            ->take($max * 2)
            ->get();

        /**
         * Get the actual list of paths that will be invalidated
         */
        $paths = EdgeFlush::cdn()->getInvalidationPathsForTags($tags);

        /**
         * If it's above max, flush the whole website
         */
        if ($paths->count() > $max) {
            $this->invalidateEntireCache();

            return;
        }

        /**
         * Let's dispatch invalidations only for what's configured
         *
         */
        $this->dispatchInvalidations(
            $tags->take(config('edge-flush.invalidations.batch.size')),
        );
    }

    protected function markTagsAsObsolete(array $tags): void
    {
        Tag::whereIn('tag', $tags)->update(['obsolete' => true]);
    }

    protected function dispatchInvalidations(Collection $tags): void
    {
        if ($tags->isEmpty()) {
            return;
        }

        EdgeFlush::responseCache()->invalidate($tags);

        if (EdgeFlush::cdn()->invalidate($tags)) {
            // TODO: what happens here on Akamai?
            $this->deleteTags($tags);
        }
    }

    protected function invalidateEntireCache()
    {
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

        Tag::truncate();

        Url::whereNotNull('id')->update([
            'was_purged_at' => now(),
        ]);

        return true;
    }

    public function getCurrentUrl($request)
    {
        return $request->header('X-EDGE-FLUSH-WARMING-URL') ?? url()->full();
    }
}
