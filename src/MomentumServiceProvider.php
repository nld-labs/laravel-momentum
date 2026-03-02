<?php

declare(strict_types=1);

namespace NLD\Momentum;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Registers Momentum package services and publishable assets.
 */
class MomentumServiceProvider extends BaseServiceProvider
{
    /**
     * Register package configuration.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/momentum.php', 'momentum');
    }

    /**
     * Boot package publishing and CLI integration.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/momentum.php' => config_path('momentum.php'),
        ], 'momentum-config');

        if ($this->app['config']->get('momentum.resource_wrapping', true)) {
            JsonResource::withoutWrapping();
        }

        $this->registerConsoleCommands();
    }

    /**
     * Register package Artisan commands when running in console.
     */
    protected function registerConsoleCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Commands\MakeMomentumMiddlewareCommand::class,
        ]);
    }
}
