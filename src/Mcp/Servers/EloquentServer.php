<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp\Mcp\Servers;

use DirectoryIterator;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;

class EloquentServer extends Server
{
    protected string $name = 'Laravel Eloquent';

    protected string $version = '1.0.0';

    protected string $instructions = 'Eloquent model introspection MCP server. Inspect models for relationships, attributes, casts, scopes, and observers. Supports vendor package models.';

    /** @var array<int, class-string<\Laravel\Mcp\Server\Tool>> */
    protected array $tools = [];

    /** @var array<int, class-string<\Laravel\Mcp\Server\Resource>> */
    protected array $resources = [];

    /** @var array<int, class-string<\Laravel\Mcp\Server\Prompt>> */
    protected array $prompts = [];

    protected function boot(): void
    {
        $this->tools = $this->discoverTools();
    }

    /** @return array<int, class-string<\Laravel\Mcp\Server\Tool>> */
    protected function discoverTools(): array
    {
        $tools = [];
        $toolNamespace = (new \ReflectionClass($this))->getNamespaceName();
        $toolNamespace = Str::beforeLast($toolNamespace, '\\Servers').'\\Tools\\';

        $toolDir = new DirectoryIterator(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'Tools');

        foreach ($toolDir as $toolFile) {
            if ($toolFile->isFile() && $toolFile->getExtension() === 'php') {
                $fqcn = $toolNamespace.$toolFile->getBasename('.php');
                if (class_exists($fqcn)) {
                    $tools[] = $fqcn;
                }
            }
        }

        $configuredTools = config('eloquent-mcp.tools', []);
        foreach ($configuredTools as $toolName => $enabled) {
            if (! $enabled) {
                $toolClass = $toolNamespace.Str::studly($toolName);
                $tools = array_filter($tools, fn ($tool) => $tool !== $toolClass);
            }
        }

        return array_values($tools);
    }
}
