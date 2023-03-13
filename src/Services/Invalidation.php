<?php declare(strict_types=1);

namespace A17\EdgeFlush\Services;

use Carbon\Carbon;
use Aws\Result as AwsResult;
use A17\EdgeFlush\EdgeFlush;
use A17\EdgeFlush\Models\Url;
use A17\EdgeFlush\Models\Tag;
use Illuminate\Support\Collection;
use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Behaviours\MakeTag;
use Illuminate\Database\Eloquent\Model;
use A17\EdgeFlush\Behaviours\CastObject;

class Invalidation
{
    use MakeTag, CastObject;

    protected string|null $id = null;

    protected string|null $status = null;

    protected bool $success = false;

    protected string|null $type = null;

    protected bool $mustInvalidateAll = false;

    protected Carbon|string|null $createdAt = null;

    protected Collection $tags;

    protected Collection $urls;

    protected Collection $urlNames;

    protected Collection $paths;

    protected Collection $tagNames;

    protected Collection $models;

    protected Collection $modelNames;

    public function __construct()
    {
        $this->instantiate();
    }

    public function setId(string|null $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;

        return $this;
    }

    public function setStatus(string|null $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setCreatedAt(Carbon|string|null $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function id(): string|null
    {
        return $this->id;
    }

    public function status(): string|null
    {
        return $this->status;
    }

    public function createdAt(): string|null
    {
        return $this->status;
    }

    public function absorbCloudFront(AwsResult $invalidation): self
    {
        if (!($this->success = filled($invalidation))) {
            return $this;
        }

        $metadata = $invalidation['@metadata'];

        $statusCode = is_array($metadata) ? $metadata['statusCode'] : 500;

        $invalidation = Helpers::toArray($invalidation['Invalidation']);

        $time = $invalidation['CreateTime'] ?? null;

        $time = filled($time) ? Carbon::parse($time) : null;

        $this->setId($invalidation['Id'] ?? null)
            ->setStatus($invalidation['Status'] ?? null)
            ->setCreatedAt($time)
            ->setSuccess($statusCode === 201);

        return $this;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'Completed';
    }

    public function absorb(AwsResult $object): self
    {
        $this->absorbCloudFront($object);

        return $this;
    }

    public static function factory(AwsResult $object): self
    {
        $self = new self();

        if (blank($object)) {
            return $self;
        }

        $self->absorb($object);

        return $self;
    }

    public function toJson(): string|false
    {
        return json_encode($this->toArray());
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'success' => $this->success,
            'created_at' => (string) $this->createdAt,
        ];
    }

    public function setTags(Collection $tags): self
    {
        $this->type = 'tag';

        if ($tags->isEmpty()) {
            $this->tags = new Collection();

            return $this;
        }

        if (is_string($tags[0])) {
            $this->tagNames = $tags;
        } else {
            $this->tags = $tags;
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return blank($this->type()) ||
            ($this->tags()->isEmpty() &&
                $this->tagNames()->isEmpty() &&
                $this->models()->isEmpty() &&
                $this->modelNames()->isEmpty() &&
                $this->urls()->isEmpty() &&
                $this->urlNames()->isEmpty());
    }

    public function setModels(Collection $models): self
    {
        $this->type = 'model';

        if ($models->isEmpty()) {
            $this->models = $this->modelNames = $models;

            return $this;
        }

        if (is_string($models[0])) {
            $this->modelNames = $models;
        } else {
            $this->models = $models;

            $this->modelNames = (new Collection($models))->map(function (mixed $model) {
                if ($model instanceof Model) {
                    return $this->makeModelName($model);
                }

                return $model;
            });
        }

        return $this;
    }

    public function tags(): Collection
    {
        return $this->tags;
    }

    public function tagNames(): Collection
    {
        if (!$this->tagNames->isEmpty()) {
            return $this->tagNames;
        }

        return $this->tagNames = $this->tags()->map->tag;
    }

    public function modelNames(): Collection
    {
        if (!$this->modelNames->isEmpty()) {
            return $this->modelNames;
        }

        return $this->modelNames = $this->models()->map->model;
    }

    public function models(): Collection
    {
        return $this->models;
    }

    public function paths(): Collection
    {
        if (filled($this->paths)) {
            return $this->paths;
        }

        $items = $this->urls()->isNotEmpty() ? $this->urls() : $this->tags();

        return $this->paths = $items
            ->map(fn($item) => $this->getInvalidationPath($item))
            ->filter()
            ->unique();
    }

    public function setPaths(Collection $paths): self
    {
        $this->type = 'path';

        $this->paths = $paths;

        return $this;
    }

    public function queryItemsList(string|null $type = null): string
    {
        return $this->items($type)
            ->map(fn($item) => "'" . Helpers::toString($item) . "'")
            ->join(',');
    }

    public function items(string|null $type = null): Collection
    {
        if ((blank($type) && $this->type === 'model') || $type === 'model') {
            return $this->modelNames();
        }

        if ((blank($type) && $this->type === 'tag') || $type === 'tag') {
            return $this->tagNames();
        }

        if ((blank($type) && $this->type === 'path') || $type === 'path') {
            return $this->paths();
        }

        if ((blank($type) && $this->type === 'url') || $type === 'url') {
            return $this->urlNames();
        }

        return new Collection();
    }

    public function type(): string|null
    {
        return $this->type;
    }

    public function getInvalidationPaths(): Collection
    {
        /**
         * Get the actual list of paths that will be invalidated.
         * Never exceed the CDN max tags or urls that can be invalidated
         * at once.
         */
        return EdgeFlush::cdn()->getInvalidationPathsForTags($this);
    }

    public function setUrls(Collection $urls): self
    {
        $this->type = 'url';

        $this->urls = $urls;

        return $this;
    }

    public function urls(): Collection
    {
        return $this->urls;
    }

    protected function getInvalidationPath(mixed $item): string|null
    {
        if (is_string($item)) {
            return $item;
        }

        $url = Helpers::getUrl($item);

        if (!is_string($url)) {
            return null;
        }

        return Helpers::parseUrl($url)['path'] ?? '/*';
    }

    public function urlNames(): Collection
    {
        if (!$this->urlNames->isEmpty()) {
            return $this->urlNames;
        }

        return $this->urlNames = $this->urls()->map->url;
    }

    public function setMustInvalidateAll(bool $value = true): self
    {
        $this->mustInvalidateAll = $value;

        return $this;
    }

    public function mustInvalidateAll(): bool
    {
        return $this->mustInvalidateAll;
    }

    public function __sleep(): array
    {
        return [
            'id',
            'status',
            'success',
            'type',
            'models',
            'mustInvalidateAll',
            'modelNames',
            'tagNames',
            'urlNames',
        ];
    }

    public function __wakeup(): void
    {
        $this->instantiate();
    }

    private function instantiate(): void
    {
        $this->tags ??= new Collection();

        $this->tagNames ??= new Collection();

        $this->urls ??= new Collection();

        $this->urlNames ??= new Collection();

        $this->models ??= new Collection();

        $this->modelNames ??= new Collection();

        $this->paths ??= new Collection();
    }
}
