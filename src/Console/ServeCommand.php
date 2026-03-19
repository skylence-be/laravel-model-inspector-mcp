<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('eloquent-mcp:serve', 'Starts Laravel Eloquent MCP server (usually from .mcp.json)')]
final class ServeCommand extends Command
{
    public function handle(): int
    {
        return Artisan::call('mcp:start eloquent');
    }
}
