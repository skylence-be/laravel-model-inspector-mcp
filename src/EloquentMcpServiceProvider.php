<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp;

use Illuminate\Support\ServiceProvider;

class EloquentMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/eloquent-mcp.php',
            'eloquent-mcp'
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/ai.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/eloquent-mcp.php' => config_path('eloquent-mcp.php'),
            ], 'eloquent-mcp-config');
        }
    }
}
