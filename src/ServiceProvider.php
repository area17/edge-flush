<?php

namespace A17\CDN;

use A17\CDN\Exceptions\CDN as CDNException;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    public function boot()
    {
        $this->publishConfig();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register()
    {
        $this->mergeConfig();

        $this->configureContainer();
    }

    public function publishConfig()
    {
        $this->publishes(
            [
                __DIR__ . '/../config/cdn.php' => config_path('cdn.php'),
            ],
            'config',
        );
    }

    private function mergeConfig(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/cdn.php', 'cdn');
    }

    public function configureContainer()
    {
        $this->app->singleton('a17.cdn.service', function ($app) {
            $service = config('cdn.service');

            if (blank($service)) {
                CDNException::missingService();
            }

            if (! class_exists($service)) {
                CDNException::classNotFound($service);
            }

            return $this->app->make($service);
        });

        $this->app->singleton('a17.cdn.cache-control', function ($app) {
            return new CacheControl();
        });
    }
}
