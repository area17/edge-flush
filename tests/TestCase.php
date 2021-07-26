<?php

namespace A17\EdgeFlush\Tests;

use A17\EdgeFlush\ServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

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
        return [ServiceProvider::class];
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
