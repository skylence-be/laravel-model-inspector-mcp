<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('eloquent-mcp:serve', 'Starts Laravel Eloquent MCP server (usually from .mcp.json)')]
final class ServeCommand extends Command
{
    public function handle(): int
    {
        return $this->call('mcp:start', ['server' => 'eloquent']);
    }
}
