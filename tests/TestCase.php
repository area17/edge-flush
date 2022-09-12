<?php

namespace A17\EdgeFlush\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Database\Eloquent\Factories\Factory;
use A17\EdgeFlush\ServiceProvider as EdgeFlushServiceProvider;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn(string $modelName) => 'A17\\EdgeFlush\\Database\\Factories\\' .
                class_basename($modelName) .
                'Factory',
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            EdgeFlushServiceProvider::class
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
        include_once __DIR__.'/../database/migrations/create_cdn_table.php.stub';
        (new \CreatePackageTable())->up();
        */
    }
}
