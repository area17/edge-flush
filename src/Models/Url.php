<?php

namespace A17\EdgeFlush\Models;

use Illuminate\Database\Eloquent\Model;

class Url extends Model
{
    protected $table = 'edge_flush_urls';

    protected $fillable = ['url', 'url_hash', 'hits', 'was_purged_at'];
}
