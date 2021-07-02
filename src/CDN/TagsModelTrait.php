<?php

namespace App\Services\CDN;

trait TagsModelTrait
{
    public function getCacheTag(): string
    {
        return $this->attributes['id'] ?? false
            ? static::class . '-' . $this->attributes['id']
            : '';
    }

    // TODO: disabled in favor of a Repository afterSave() strategy,
    //       but kept code it here for further evaluation
    //
    //    public static function bootTagsModelTrait()
    //    {
    //        static::updated(function ($model) {
    //            app(Tags::class)->purgeTagsFor($model);
    //        });
    //
    //        static::deleted(function ($model) {
    //            app(Tags::class)->purgeTagsFor($model);
    //        });
    //    }

    public function getAttribute($key)
    {
        app(Tags::class)->addTag($this);

        return parent::getAttribute($key);
    }
}
