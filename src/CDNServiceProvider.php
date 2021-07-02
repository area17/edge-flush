<?php

namespace Area17\CDN;

use Area17\CDN\Commands\CDNCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CDNServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('cdn')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_cdn_table')
            ->hasCommand(CDNCommand::class);
    }
}
