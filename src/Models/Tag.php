<?php

namespace A17\CDN\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $table = 'cdn_cache_tags';

    protected $fillable = ['model', 'tag', 'url', 'url_hash'];
}
