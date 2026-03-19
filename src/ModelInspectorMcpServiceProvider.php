<?php

declare(strict_types=1);

namespace Skylence\ModelInspectorMcp;

use Illuminate\Support\ServiceProvider;
use Skylence\ModelInspectorMcp\Console\InstallCommand;
use Skylence\ModelInspectorMcp\Console\ServeCommand;
use Skylence\ModelInspectorMcp\Support\Config;

class ModelInspectorMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/model-inspector-mcp.php',
            'model-inspector-mcp'
        );

        $this->app->singleton(Config::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('local', 'testing')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/ai.php');
        }

        $this->commands([
            InstallCommand::class,
            ServeCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/model-inspector-mcp.php' => config_path('model-inspector-mcp.php'),
            ], 'model-inspector-mcp-config');
        }
    }
}
