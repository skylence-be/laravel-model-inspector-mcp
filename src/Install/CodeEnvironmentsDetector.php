<?php

declare(strict_types=1);

namespace Skylence\EloquentMcp\Install;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Skylence\EloquentMcp\Install\CodeEnvironment\CodeEnvironment;
use Skylence\EloquentMcp\Install\Detection\DetectionStrategyFactory;
use Skylence\EloquentMcp\Install\Enums\Platform;

class CodeEnvironmentsDetector
{
    public function __construct(
        private readonly Container $container,
        private readonly DetectionStrategyFactory $strategyFactory
    ) {}

    /**
     * Get all available code environments.
     *
     * @return array<int, class-string<CodeEnvironment>>
     */
    protected function getCodeEnvironmentClasses(): array
    {
        return [
            \Skylence\EloquentMcp\Install\CodeEnvironment\ClaudeCode::class,
            \Skylence\EloquentMcp\Install\CodeEnvironment\Cursor::class,
            \Skylence\EloquentMcp\Install\CodeEnvironment\VSCode::class,
            \Skylence\EloquentMcp\Install\CodeEnvironment\PhpStorm::class,
        ];
    }

    /**
     * Detect installed applications on the current platform.
     *
     * @return array<string>
     */
    public function discoverSystemInstalledCodeEnvironments(): array
    {
        $platform = Platform::current();

        return $this->getCodeEnvironments()
            ->filter(fn (CodeEnvironment $program): bool => $program->detectOnSystem($platform))
            ->map(fn (CodeEnvironment $program): string => $program->name())
            ->values()
            ->toArray();
    }

    /**
     * Detect applications used in the current project.
     *
     * @return array<string>
     */
    public function discoverProjectInstalledCodeEnvironments(string $basePath): array
    {
        return $this->getCodeEnvironments()
            ->filter(fn (CodeEnvironment $program): bool => $program->detectInProject($basePath))
            ->map(fn (CodeEnvironment $program): string => $program->name())
            ->values()
            ->toArray();
    }

    /**
     * Get all registered code environments.
     *
     * @return Collection<string, CodeEnvironment>
     */
    public function getCodeEnvironments(): Collection
    {
        return collect($this->getCodeEnvironmentClasses())
            ->map(fn (string $className) => new $className($this->strategyFactory));
    }
}
