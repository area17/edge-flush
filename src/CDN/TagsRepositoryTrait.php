<?php

namespace App\Services\CDN;

trait TagsRepositoryTrait
{
    public function invalidateEdgeCacheTags($model)
    {
        app(Tags::class)->purgeTagsFor($model);
    }
}
