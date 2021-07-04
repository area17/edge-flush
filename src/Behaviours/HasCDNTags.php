<?php

namespace A17\CDN\Behaviours;

use A17\CDN\CDN;

trait HasCDNTags
{
    public function invalidateCDNTags($model)
    {
        CDN::tags()->purgeTagsFor($model);
    }

    public function getCDNCacheTag(): string
    {
        return $this->attributes['id'] ?? false
            ? static::class . '-' . $this->attributes['id']
            : '';
    }

    public function getAttribute($key)
    {
        CDN::tags()->addTag($this);

        return parent::getAttribute($key);
    }
}
