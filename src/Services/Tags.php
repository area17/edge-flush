<?php

namespace A17\CDN\Services;

use A17\CDN\CDN;
use A17\CDN\Models\Tag;
use A17\CDN\Models\Url;
use A17\CDN\Jobs\StoreTags;
use Illuminate\Support\Str;
use A17\CDN\Jobs\InvalidateTags;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class Tags
{
    protected array $tags = [];

    public $processedTags = [];

    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function addTag(Model $model): void
    {
        if (
            $this->wasNotProcessed($model) &&
            CDN::enabled() &&
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
            'cdn_cache_tags',
            'cdn_cache_tags.url_id',
            '=',
            'cdn_cache_urls.id',
        )
            ->whereIn('cdn_cache_tags.tag', $tags)
            ->update([
                'was_purged_at' => now(),
            ]);
    }

    /**
     * @param string|null $tag
     * @return mixed
     */
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

    /**
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function getTagsHash(Response $response)
    {
        $models = $this->getTags();

        /**
         * @psalm-suppress InvalidScalarArgument
         */
        $tag = str_replace(
            ['%environment%', '%sha1%'],
            [app()->environment(), sha1(collect($models)->join(', '))],
            config('cdn.tags.format'),
        );

        if (CDN::cacheControl()->isCachable($response)) {
            StoreTags::dispatch($models, $tag, url()->full());
        }

        return $tag;
    }

    public function makeTag(Model $model): ?string
    {
        $tag = null;

        try {
            $tag = method_exists($model, 'getCDNCacheTag')
                ? $model->getCDNCacheTag()
                : null;
        } catch (\Exception $exception) {
            // TODO: should we ignore errors here?
        }

        return $tag;
    }

    protected function tagIsExcluded(string $tag): bool
    {
        /**
         * @param callable(string $pattern): boolean $pattern
         */
        return collect(config('cdn.tags.excluded-model-classes'))->contains(
            fn(string $pattern) => CDN::match($pattern, $tag),
        );
    }

    protected function tagIsNotExcluded(string $tag): bool
    {
        return !$this->tagIsExcluded($tag);
    }

    public function storeCacheTags(
        array $models,
        string $tag,
        string $url
    ): void {
        collect($models)->each(function (string $model) use ($tag, $url) {
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

            $tag = Tag::firstOrCreate([
                'model' => $model,
                'tag' => $tag,
                'url_id' => $url->id,
            ]);
        });
    }

    public function invalidateTagsFor(Model $model): void
    {
        $tags = $this->getAllTagsForModel($this->makeTag($model))
            ->pluck('tag')
            ->toArray();

        if (filled($tags)) {
            InvalidateTags::dispatch($tags);
        }
    }

    public function invalidateCacheTags($tags = null): void
    {
        if (blank($tags)) {
            $this->invalidateObsoleteTags();

            return;
        }

        if (config('cdn.invalidations.type') === 'batch') {
            $this->makeTagsObsolete($tags);

            return;
        }

        $this->dispatchInvalidations(Tag::whereIn('tag', $tags)->get());
    }

    protected function invalidateObsoleteTags(): void
    {
        $count = Tag::where('obsolete', true)->count();

        if ($count > config('cdn.invalidations.batch.max_tags')) {
            $this->invalidateEntireCache();

            return;
        }

        $this->dispatchInvalidations(Tag::where('obsolete', true)->get());
    }

    protected function makeTagsObsolete(array $tags): void
    {
        Tag::whereIn('tag', $tags)->update(['obsolete' => true]);
    }

    protected function dispatchInvalidations(Collection $tags): void
    {
        if (CDN::cdn()->invalidate($tags)) {
            // TODO: what happens here on Akamai?
            $this->deleteTags($tags);
        }
    }

    protected function invalidateEntireCache()
    {
        CDN::cdn()->invalidate(
            collect(config('cdn.invalidations.batch.site_roots')),
        );
    }

    /*
     * Optimized for speed, 2000 calls to CDN::tags()->addTag($model) are now only 8ms
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
}
