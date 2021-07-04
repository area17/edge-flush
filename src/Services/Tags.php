<?php

namespace A17\CDN\Services;

use A17\CDN\CDN;
use A17\CDN\Models\Tag;
use A17\CDN\Jobs\PurgeTags;
use A17\CDN\Jobs\StoreTags;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class Tags
{
    protected $tags;

    public function addTag($data)
    {
        if (CDN::enabled() && filled($tag = $this->makeTag($data))) {
            $this->tags[$tag] = $tag;
        }
    }

    protected function deleteTag($tag): void
    {
        Tag::where('tag', $tag)->delete();
    }

    protected function getAllTagsFor(string $tag)
    {
        return Tag::where('tag', $tag)->get();
    }

    public function getTags()
    {
        return collect($this->tags)
            ->reject(function ($tag) {
                return $this->tagIsExcluded($tag);
            })
            ->values();
    }

    public function getTagsHash($response)
    {
        $models = $this->getTags();

        $tag = str_replace(
            ['%environment%', '%sha1%'],
            [app()->environment(), sha1($models->join(', '))],
            config('cdn.tags.format'),
        );

        if (CDN::cacheControl()->isCachable($response)) {
            StoreTags::dispatch($models, $tag, url()->full());
        }

        return $tag;
    }

    public function makeTag($model): ?string
    {
        $tag = null;

        try {
            $tag =
                $model instanceof Model &&
                method_exists($model, 'getCDNCacheTag')
                    ? $model->getCDNCacheTag()
                    : null;
        } catch (\Exception $exception) {
            // TODO: should we ignore errors here?
        }

        return $tag;
    }

    protected function purgeTagsFromCDNService(Collection $keys)
    {
        CDN::cdnService()->purge($keys);
    }

    protected function recursivelyCreateTags($data)
    {
        if ($data instanceof Model) {
            $this->addTag($data);

            return;
        }

        if (is_traversable($data)) {
            collect($data)->each(function ($item) {
                $this->recursivelyCreateTags($item);
            });
        }
    }

    protected function tagIsExcluded(string $tag): bool
    {
        return collect(config('cdn.tags.excluded-model-classes'))->contains(
            fn($pattern) => CDN::match($pattern, $tag),
        );
    }

    protected function tagIsNotExcluded(string $tag): bool
    {
        return !$this->tagIsExcluded($tag);
    }

    public function storeCacheTags($models, $tag, $url)
    {
        collect($models)->each(
            fn($model) => Tag::firstOrCreate([
                'model' => $model,
                'tag' => $tag,
                'url' => Str::limit($url, 255),
                'url_hash' => sha1($url),
            ]),
        );
    }

    public function purgeTagsFor($model)
    {
        $tags = $this->getAllTagsFor($this->makeTag($model))->pluck(
            'tag',
            'url_hash',
        );

        if (filled($tags)) {
            PurgeTags::dispatch($tags);
        }
    }

    public function purgeCacheTags($tags)
    {
        DB::transaction(
            fn() => collect($tags)->each(fn($tag) => $this->deleteTag($tag)),
        );

        $this->purgeTagsFromCDNService(collect($tags)->keys());
    }
}
