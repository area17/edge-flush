<?php

namespace A17\EdgeFlush;

use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Services\EdgeFlush;
use A17\EdgeFlush\EdgeFlush as EdgeFlushFacade;
use A17\EdgeFlush\Exceptions\EdgeFlush as EdgeFlushException;
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
            $service = Helpers::configString('edge-flush.classes.cdn') ?? '';

            if (blank($service)) {
                EdgeFlushException::missingService();
            }

            if (!class_exists($service)) {
                EdgeFlushException::classNotFound($service);
            }

            return new EdgeFlush(
                $service,
                $app->make(config('edge-flush.classes.cache-control')),
                $app->make(config('edge-flush.classes.tags')),
                $app->make(config('edge-flush.classes.warmer')),
            );
        });

        $this->app->singleton('a17.edge-flush.cache-control', function () {
            return EdgeFlushFacade::cacheControl();
        });
    }
}
