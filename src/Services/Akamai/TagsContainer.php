<?php

namespace A17\CDN\Services;

use A17\CDN\Contracts\Transformer as TransformerContract;
use App\Jobs\PurgeEdgeCacheTags;
use App\Jobs\StoreEdgeCacheTags;
use App\Models\EdgeCacheTag;
use App\Services\Akamai\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TagsContainer
{
    protected $tags;

    protected $excluded = [
        '\Models\Translations\\',
        '\Models\Slugs\\',
        '\Models\Revisions\\',
    ];

    protected $enable;

    public function addTag($data)
    {
        if (filled($tag = $this->makeTag($data))) {
            $this->tags[$tag] = $tag;
        }
    }

    /**
     * @param $tag
     */
    protected function deleteEdgeCacheTag($tag): void
    {
        EdgeCacheTag::where('tag', $tag)->delete();
    }

    public function enabled()
    {
        // Only enable cache tags generation on pages that will be cached
        return $this->enable && cache_control()->enabled();
    }

    public function disabled()
    {
        return ! $this->enabled();
    }

    protected function getAllTagsFor(string $tag)
    {
        return EdgeCacheTag::where('tag', $tag)->get();
    }

    protected function getData($object)
    {
        if ($object instanceof TransformerContract) {
            return $object->getData();
        }

        return $object;
    }

    /**
     * @return mixed
     */
    public function getTags()
    {
        return collect($this->tags)
            ->reject(function ($tag) {
                return $this->tagIsExcluded($tag);
            })
            ->values();
    }

    public function getTagsHash()
    {
        $tags = $this->getTags();

        $hash = sprintf(
            'flv-%s-%s',
            app()->environment(),
            sha1($tags->join(', ')),
        );

        if ($this->responseIsCachable()) {
            StoreEdgeCacheTags::dispatch($tags, $hash);
        }

        return $hash;
    }

    /**
     * @param mixed|\App\Models\Model $tag
     * @return string|null
     */
    public function makeTag($tag): ?string
    {
        try {
            $tag = $tag instanceof Model ? $tag->getCacheTag() : null;
        } catch (\Exception $exception) {
            $tag = null;
        }

        return $tag;
    }

    /**
     * @param $model
     * @return bool
     */
    protected function isTaggable($model): ?bool
    {
        return $model instanceof Model &&
            ! (
                $model instanceof \App\Models\Translations\Model ||
                $model instanceof \App\Models\Slugs\Model ||
                $model instanceof \App\Models\Revisions\Model
            );
    }

    protected function purgeTagsFromCDN(\Illuminate\Support\Collection $keys)
    {
        app(Service::class)->purge($keys);
    }

    protected function recursivelyCreateTags($data)
    {
        if ($this->isTaggable($data)) {
            $this->addTag($data);

            return;
        }

        if (is_traversable($data)) {
            collect($data)->each(function ($item) {
                $this->recursivelyCreateTags($item);
            });
        }
    }

    /**
     * @return \A17\CDN\TagsContainer
     */
    public function enable(): self
    {
        $this->enable = true;

        return $this;
    }

    /**
     * @return \A17\CDN\TagsContainer
     */
    public function disable(): self
    {
        $this->enable = false;

        return $this;
    }

    protected function responseIsCachable(): bool
    {
        return cache_control()->responseIsCachable();
    }

    protected function tagIsExcluded(string $tag): bool
    {
        return Str::contains($tag, $this->excluded);
    }

    protected function tagIsNotExcluded(string $tag): bool
    {
        return ! $this->tagIsExcluded($tag);
    }

    public function storeCacheTags($tags, $hash)
    {
        collect($tags)->each(
            fn ($tag) => EdgeCacheTag::firstOrCreate([
                'tag' => $tag,
                'page_hash' => $hash,
            ]),
        );
    }

    public function purgeTagsFor($model)
    {
        $tags = $this->getAllTagsFor($this->makeTag($model))->pluck(
            'tag',
            'page_hash',
        );

        if (filled($tags)) {
            info(['purging', $this->makeTag($model), $tags]);

            PurgeEdgeCacheTags::dispatch($tags);
        }
    }

    public function purgeCacheTags($tags)
    {
        DB::transaction(
            fn () => collect($tags)->each(
                fn ($tag) => $this->deleteEdgeCacheTag($tag),
            ),
        );

        $this->purgeTagsFromCDN(collect($tags)->keys());
    }
}
