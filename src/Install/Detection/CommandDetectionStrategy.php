<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp\Install\Detection;

use Illuminate\Support\Facades\Process;
use Skylence\EloquentMcp\Install\Contracts\DetectionStrategy;
use Skylence\EloquentMcp\Install\Enums\Platform;

class CommandDetectionStrategy implements DetectionStrategy
{
    public function detect(array $config, ?Platform $platform = null): bool
    {
        if (! isset($config['command'])) {
            return false;
        }

        try {
            return Process::timeout(3)->run($config['command'])->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
