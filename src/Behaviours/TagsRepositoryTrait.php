<?php

namespace A17\CDN;

trait TagsRepositoryTrait
{
    public function invalidateEdgeCacheTags($model)
    {
        app(Tags::class)->purgeTagsFor($model);
    }
}
