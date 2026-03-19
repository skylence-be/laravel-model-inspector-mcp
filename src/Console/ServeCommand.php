<?php

declare(strict_types=1);

namespace Skylence\ModelInspectorMcp\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('model-inspector:mcp', 'Starts Laravel Model Inspector MCP server (usually from .mcp.json)')]
final class ServeCommand extends Command
{
    public function handle(): int
    {
        return $this->call('mcp:start', ['server' => 'eloquent']);
    }
}
