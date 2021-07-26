<?php

namespace A17\EdgeFlush\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tag extends Model
{
    protected $table = 'edge_flush_tags';

    protected $fillable = ['model', 'tag', 'url_id', 'response_cache_hash'];

    protected $with = ['url'];

    public function url(): BelongsTo
    {
        return $this->belongsTo(Url::class);
    }
}
