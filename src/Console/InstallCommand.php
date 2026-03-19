<?php

declare(strict_types=1);

namespace Skylence\ModelInspectorMcp\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Terminal;
use Skylence\ModelInspectorMcp\Contracts\McpClient;
use Skylence\ModelInspectorMcp\Install\CodeEnvironment\CodeEnvironment;
use Skylence\ModelInspectorMcp\Install\CodeEnvironmentsDetector;
use Skylence\ModelInspectorMcp\Install\Mcp\FileWriter;
use Skylence\ModelInspectorMcp\Support\Config;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;

#[AsCommand('model-inspector:install', 'Install Laravel Model Inspector MCP server into your IDE')]
final class InstallCommand extends Command
{
    use Colors;

    private CodeEnvironmentsDetector $codeEnvironmentsDetector;

    private Terminal $terminal;

    /** @var Collection<int, McpClient> */
    private Collection $selectedTargetMcpClient;

    private string $projectName;

    private bool $useSail = false;

    /** @var array<string> */
    private array $systemInstalledCodeEnvironments = [];

    /** @var array<string> */
    private array $projectInstalledCodeEnvironments = [];

    private string $greenTick;

    private string $redCross;

    public function __construct(protected Config $config)
    {
        parent::__construct();
    }

    public function handle(CodeEnvironmentsDetector $codeEnvironmentsDetector, Terminal $terminal): int
    {
        $this->bootstrap($codeEnvironmentsDetector, $terminal);

        $this->displayHeader();
        $this->discoverEnvironment();
        $this->collectPreferences();
        $this->performInstallation();
        $this->outro();

        return Command::SUCCESS;
    }

    protected function bootstrap(CodeEnvironmentsDetector $codeEnvironmentsDetector, Terminal $terminal): void
    {
        $this->codeEnvironmentsDetector = $codeEnvironmentsDetector;
        $this->terminal = $terminal;
        $this->terminal->initDimensions();

        $this->greenTick = $this->green('✓');
        $this->redCross = $this->red('✗');
        $this->selectedTargetMcpClient = collect();
        $this->projectName = config('app.name', 'Laravel');
    }

    protected function displayHeader(): void
    {
        intro('Laravel Model Inspector MCP :: Install');
        note(sprintf("Let's configure %s with Eloquent MCP", $this->bgYellow($this->black($this->bold($this->projectName)))));
    }

    protected function discoverEnvironment(): void
    {
        $this->systemInstalledCodeEnvironments = $this->codeEnvironmentsDetector->discoverSystemInstalledCodeEnvironments();
        $this->projectInstalledCodeEnvironments = $this->codeEnvironmentsDetector->discoverProjectInstalledCodeEnvironments(base_path());
    }

    protected function collectPreferences(): void
    {
        if ($this->isSailInstalled() && ($this->isRunningInsideSail() || $this->shouldConfigureSail())) {
            $this->useSail = true;
        }

        $this->selectedTargetMcpClient = $this->selectTargetMcpClients();
    }

    protected function performInstallation(): void
    {
        if ($this->selectedTargetMcpClient->isEmpty()) {
            $this->info('No editors selected for MCP installation.');

            return;
        }

        $this->newLine();
        $this->info(' Installing MCP servers to your selected IDEs');
        $this->newLine();

        usleep(750000);

        $failed = [];
        $longestIdeName = max(
            1,
            ...$this->selectedTargetMcpClient->map(
                fn (McpClient $mcpClient) => Str::length($mcpClient->mcpClientName())
            )->toArray()
        );

        /** @var McpClient $mcpClient */
        foreach ($this->selectedTargetMcpClient as $mcpClient) {
            $ideName = $mcpClient->mcpClientName();
            $ideDisplay = str_pad((string) $ideName, $longestIdeName);
            $this->output->write("  {$ideDisplay}... ");

            $mcp = $this->buildMcpCommand($mcpClient);

            try {
                $result = $this->installMcpServer($mcpClient, $mcp);

                if ($result) {
                    $this->line($this->greenTick);
                } else {
                    $this->line($this->redCross);
                    $failed[$ideName] = 'Failed to write configuration';
                }
            } catch (Exception $e) {
                $this->line($this->redCross);
                $failed[$ideName] = $e->getMessage();
            }
        }

        $this->newLine();

        if ($failed !== []) {
            $this->error(sprintf('%s Some MCP servers failed to install:', $this->redCross));

            foreach ($failed as $ideName => $error) {
                $this->line("  - {$ideName}: {$error}");
            }
        }

        $this->config->setSail($this->useSail);
        $this->config->setEditors(
            $this->selectedTargetMcpClient->map(fn (McpClient $mcpClient): string => $mcpClient->name())->values()->toArray()
        );
    }

    protected function shouldConfigureSail(): bool
    {
        return confirm(
            label: 'Laravel Sail detected. Configure Eloquent MCP to use Sail?',
            default: $this->config->getSail(),
            hint: 'This will configure the MCP server to run through Sail.',
        );
    }

    /**
     * @return Collection<int, CodeEnvironment>
     */
    protected function selectTargetMcpClients(): Collection
    {
        $allEnvironments = $this->codeEnvironmentsDetector->getCodeEnvironments();
        $availableEnvironments = $allEnvironments->filter(fn (CodeEnvironment $env): bool => $env instanceof McpClient);

        if ($availableEnvironments->isEmpty()) {
            return collect();
        }

        $options = $availableEnvironments->mapWithKeys(fn (CodeEnvironment $env): array => [$env->name() => $env->displayName()])->sort();

        $installedEnvNames = array_unique(array_merge(
            $this->projectInstalledCodeEnvironments,
            $this->systemInstalledCodeEnvironments
        ));

        $defaults = $this->config->getEditors();
        $detectedDefaults = [];

        if ($defaults === []) {
            foreach ($installedEnvNames as $envKey) {
                $matchingEnv = $availableEnvironments->first(fn (CodeEnvironment $env): bool => strtolower((string) $envKey) === strtolower($env->name()));
                if ($matchingEnv) {
                    $detectedDefaults[] = $matchingEnv->name();
                }
            }
        }

        $selectedCodeEnvironments = collect(multiselect(
            label: sprintf('Which code editors do you use to work on %s?', $this->projectName),
            options: $options->toArray(),
            default: $defaults === [] ? $detectedDefaults : $defaults,
            scroll: $options->count(),
            required: true,
            hint: $detectedDefaults !== [] ? sprintf('Auto-detected %s', Arr::join(array_map(
                fn ($name) => $availableEnvironments->first(fn ($env) => $env->name() === $name)?->displayName() ?? $name,
                $detectedDefaults
            ), ', ', ' & ')) : '',
        ))->sort();

        return $selectedCodeEnvironments->map(
            fn (string $name) => $availableEnvironments->first(fn ($env): bool => $env->name() === $name),
        )->filter()->values();
    }

    /**
     * @return array<int, string>
     */
    protected function buildMcpCommand(McpClient $mcpClient): array
    {
        if ($this->useSail) {
            return ['laravel-model-inspector-mcp', './vendor/bin/sail', 'artisan', 'model-inspector:mcp'];
        }

        $inWsl = $this->isRunningInWsl();

        return array_filter([
            'laravel-model-inspector-mcp',
            $inWsl ? 'wsl' : false,
            $mcpClient->getPhpPath($inWsl),
            $mcpClient->getArtisanPath($inWsl),
            'model-inspector:mcp',
        ]);
    }

    /**
     * @param  array<int, string>  $mcp
     */
    protected function installMcpServer(McpClient $mcpClient, array $mcp): bool
    {
        $path = $mcpClient->mcpConfigPath();
        if (! $path) {
            return $mcpClient->installMcp(
                array_shift($mcp),
                array_shift($mcp),
                $mcp
            );
        }

        $writer = new FileWriter($path);
        $writer->configKey($mcpClient->mcpConfigKey());

        $localKey = array_shift($mcp);
        $command = array_shift($mcp);
        $writer->addServer($localKey, $command, $mcp, []);

        return $writer->save();
    }

    protected function outro(): void
    {
        $this->newLine();
        $this->info(' Installation complete! Restart your IDE to activate the MCP server.');
        $this->newLine();
    }

    protected function isSailInstalled(): bool
    {
        return file_exists(base_path('vendor/bin/sail'))
            && (file_exists(base_path('docker-compose.yml')) || file_exists(base_path('compose.yaml')));
    }

    protected function isRunningInsideSail(): bool
    {
        return get_current_user() === 'sail' || getenv('LARAVEL_SAIL') === '1';
    }

    private function isRunningInWsl(): bool
    {
        return ! empty(getenv('WSL_DISTRO_NAME')) || ! empty(getenv('IS_WSL'));
    }
}
