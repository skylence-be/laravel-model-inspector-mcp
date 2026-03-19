<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp\Install\Enums;

enum McpInstallationStrategy: string
{
    case FILE = 'file';
    case SHELL = 'shell';
    case NONE = 'none';
}
