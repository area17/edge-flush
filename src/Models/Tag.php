<?php

namespace A17\CDN\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tag extends Model
{
    protected $table = 'cdn_cache_tags';

    protected $fillable = ['model', 'tag', 'url_id'];

    protected $with = ['url'];

    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }
}
