<?php declare(strict_types=1);

namespace A17\EdgeFlush\Models;

use A17\EdgeFlush\Behaviours\CachedOnCDN;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Model extends Eloquent
{
    use CachedOnCDN;
}
