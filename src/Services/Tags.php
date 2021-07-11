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
use Symfony\Component\HttpFoundation\Response;

class Tags
{
    protected array $tags = [];

    /**
     * @psalm-suppress ArgumentTypeCoercion
     */
    public function addTag(object $data): void
    {
        if (CDN::enabled() && filled($tag = $this->makeTag($data))) {
            $this->tags[$tag] = $tag;
        }
    }

    protected function deleteTag(string $tag): void
    {
        Tag::where('tag', $tag)->delete();
    }

    /**
     * @param string|null $tag
     * @return mixed
     */
    protected function getAllTagsForModel(?string $tag)
    {
        if (filled($tag)) {
            return Tag::where('model', $tag)->get();
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

    protected function purgeTagsFromCDNService(Collection $keys): void
    {
        CDN::cdn()->purge($keys);
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
        collect($models)->each(
            fn(string $model) => Tag::firstOrCreate([
                'model' => $model,
                'tag' => $tag,
                'url' => Str::limit($url, 255),
                'url_hash' => sha1($url),
            ]),
        );
    }

    public function purgeTagsFor(Model $model): void
    {
        $tags = $this->getAllTagsForModel($this->makeTag($model))
            ->pluck('tag')
            ->toArray();

        if (filled($tags)) {
            PurgeTags::dispatch($tags);
        }
    }

    public function purgeCacheTags(array $tags): void
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

    protected function dispatchInvalidations(array $tags): void
    {
        DB::transaction(
            fn() => collect($tags)->each(
                fn(string $tag) => $this->deleteTag($tag),
            ),
        );

        $this->purgeTagsFromCDNService(collect($tags)->keys());
    }
}
