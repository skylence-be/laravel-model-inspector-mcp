<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp;

use Illuminate\Support\ServiceProvider;
use Skylence\EloquentMcp\Console\InstallCommand;
use Skylence\EloquentMcp\Console\ServeCommand;
use Skylence\EloquentMcp\Support\Config;

class EloquentMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/eloquent-mcp.php',
            'eloquent-mcp'
        );

        $this->app->singleton(Config::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/ai.php');

        $this->commands([
            InstallCommand::class,
            ServeCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/eloquent-mcp.php' => config_path('eloquent-mcp.php'),
            ], 'eloquent-mcp-config');
        }
    }
}
