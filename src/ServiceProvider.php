<?php declare(strict_types=1);

namespace A17\EdgeFlush;

use A17\EdgeFlush\Support\Helpers;
use A17\EdgeFlush\Services\EdgeFlush;
use Illuminate\Support\Facades\Event;
use A17\EdgeFlush\Listeners\EloquentSaved;
use A17\EdgeFlush\EdgeFlush as EdgeFlushFacade;
use A17\EdgeFlush\Console\Commands\InvalidateAll;
use A17\EdgeFlush\Console\Commands\ConfigListSections;
use A17\EdgeFlush\Console\Commands\ConfigMergeSection;
use A17\EdgeFlush\Exceptions\EdgeFlush as EdgeFlushException;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

class ServiceProvider extends IlluminateServiceProvider
{
    protected string $packageName = 'edge-flush';

    protected array $configSections = [
        'domains',
        'strategies',
        'classes',
        'routes',
        'invalidations',
        'warmer',
        'responses',
        'frontend-checker',
    ];

    public function boot(): void
    {
        $this->bootConfig();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadCommands();

        $this->bootEventListeners();
    }

    public function register(): void
    {
        $this->registerConfig();

        $this->configureContainer();
    }

    public function bootConfig(): void
    {
        $this->publishes([
            $this->mainConfigPath => config_path("{$this->packageName}.php", 'config'),
        ]);
    }

    public function registerConfig(): void
    {
        $this->registerMainConfig();

        $this->registerConfigSections();

        $this->enabled = config('twill-firewall.enabled');
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

    public function loadCommands(): void
    {
        $this->commands([InvalidateAll::class]);

        $this->commands([ConfigListSections::class]);

        $this->commands([ConfigMergeSection::class]);
    }

    public function bootEventListeners(): void
    {
        Event::listen('eloquent.saved: *', EloquentSaved::class);
    }

    public function registerMainConfig(): void
    {
        $this->mainConfigPath = __DIR__ . "/../config/{$this->packageName}.php";

        $this->mergeConfigFrom($this->mainConfigPath, $this->packageName);
    }

    public function registerConfigSections(): void
    {
        foreach ($this->configSections as $section) {
            $this->mergeConfigFrom(
                __DIR__ . "/../config/{$section}.php",
                "{$this->packageName}"
            );
        }

        config(["{$this->packageName}.package.name" => $this->packageName]);

        config(["{$this->packageName}.package.sections" => $this->configSections]);
    }
}
