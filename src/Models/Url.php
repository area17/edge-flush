<?php

namespace A17\CDN\Models;

use Illuminate\Database\Eloquent\Model;

class Url extends Model
{
    protected $table = 'cdn_cache_urls';

    protected $fillable = ['url', 'url_hash', 'hits', 'was_purged_at'];
}
