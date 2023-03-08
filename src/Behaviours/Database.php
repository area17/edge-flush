<?php declare(strict_types=1);

namespace A17\EdgeFlush\Behaviours;

use Illuminate\Support\Facades\DB;
use A17\EdgeFlush\Services\Invalidation;
use A17\EdgeFlush\Exceptions\EdgeFlush as EdgeFlushException;

trait Database
{
    public function isMySQL(): bool
    {
        $this->checkConnection();

        return DB::connection()->getDriverName() === 'mysql';
    }

    public function isPostgreSQL(): bool
    {
        $this->checkConnection();

        return DB::connection()->getDriverName() === 'pgsql';
    }

    public function checkConnection(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'mysql' && $driver !== 'pgsql') {
            throw new EdgeFlushException('EdgeFlush only supports MySQL and PostgreSQL databases.');
        }
    }
}
