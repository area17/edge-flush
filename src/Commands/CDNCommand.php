<?php

namespace Area17\CDN\Commands;

use Illuminate\Console\Command;

class CDNCommand extends Command
{
    public $signature = 'cdn';

    public $description = 'My command';

    public function handle()
    {
        $this->comment('All done');
    }
}
