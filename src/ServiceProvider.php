<?php

namespace A17\EdgeFlush;

use A17\EdgeFlush\Services\EdgeFlush;
use A17\EdgeFlush\Services\BaseService;
use A17\EdgeFlush\Services\CacheControl;
use A17\EdgeFlush\EdgeFlush as EdgeFlushFacade;
use A17\EdgeFlush\Exceptions\EdgeFlush as EdgeFlushxception;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    public function boot(): void
    {
        $this->publishConfig();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function register(): void
    {
        $this->mergeConfig();

        $this->configureContainer();
    }

    public function publishConfig(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../config/edge-flush.php' => config_path(
                    'edge-flush.php',
                ),
            ],
            'config',
        );
    }

    protected function mergeConfig(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/edge-flush.php',
            'edge-flush',
        );
    }

    public function configureContainer(): void
    {
        $this->app->singleton('a17.edge-flush.service', function ($app) {
            $service = config('edge-flush.classes.cdn');

            if (blank($service)) {
                EdgeFlushxception::missingService($service);
            }

            if (!class_exists($service)) {
                EdgeFlushxception::classNotFound($service);
            }

            return new EdgeFlush(
                app($service),
                $app->make(config('edge-flush.classes.cache-control')),
                $app->make(config('edge-flush.classes.tags')),
                $app->make(config('edge-flush.classes.warmer')),
                $this->instantiateResponseCache(),
            );
        });

        $this->app->singleton('a17.edge-flush.cache-control', function () {
            return EdgeFlushFacade::cacheControl();
        });
    }

    function instantiateResponseCache(): BaseService|null
    {
        if (
            blank($class = config('edge-flush.classes.response-cache')) ||
            !class_exists($class)
        ) {
            return null;
        }

        return $this->app->make($class);
    }
}
