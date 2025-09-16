<?php

namespace Finq\LaravelPartitionManager;

use Illuminate\Support\ServiceProvider;
use Finq\LaravelPartitionManager\Services\PartitionManager;

class PartitionManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/partition-manager.php',
            'partition-manager'
        );

        $this->app->singleton('partition-manager', function ($app) {
            return new PartitionManager($app['db']);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/partition-manager.php' => config_path('partition-manager.php'),
            ], 'partition-manager-config');
        }
    }

    public function provides(): array
    {
        return ['partition-manager'];
    }
}