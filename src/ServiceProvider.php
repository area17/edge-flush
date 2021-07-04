<?php

namespace A17\CDN;

use A17\CDN\Services\CDN;
use A17\CDN\Services\CacheControl;
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
            $service = config('cdn.classes.cdn');

            if (blank($service)) {
                CDNException::missingService($service);
            }

            if (!class_exists($service)) {
                CDNException::classNotFound($service);
            }

            $cacheControl = $this->app->make(
                config('cdn.classes.cache-control'),
            );

            $tags = $this->app->make(config('cdn.classes.tags'));

            return new CDN(app($service), $cacheControl, $tags);
        });

        $this->app->singleton('a17.cdn.cache-control', function ($app) {
            return $this->app->make('a17.cdn.service')->cacheControl();
        });

        $this->app->singleton('a17.cdn.tags', function ($app) {
            return $this->app->make('a17.cdn.service')->tags();
        });
    }
}
