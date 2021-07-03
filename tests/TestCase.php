<?php

namespace A17\CDN\Tests;

use A17\CDN\CDNServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'A17\\CDN\\Database\\Factories\\' .
                class_basename($modelName) .
                'Factory',
        );
    }

    protected function getPackageProviders($app)
    {
        return [CDNServiceProvider::class];
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
