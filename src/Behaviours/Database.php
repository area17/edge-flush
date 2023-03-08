<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\Services\Invalidation;
use Illuminate\Support\Facades\DB;

trait Database
{
    public function isMySQL()
    {
        $this->checkConnection();

        return DB::connection()->getDriverName() === 'mysql';
    }

    public function isPostgreSQL()
    {
        $this->checkConnection();

        return DB::connection()->getDriverName() === 'pgsql';
    }

    public function checkConnection()
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'mysql' && $driver !== 'pgsql') {
            throw new EdgeFlushException(
                'EdgeFlush only supports MySQL and PostgreSQL databases.',
            );
        }
    }
}
