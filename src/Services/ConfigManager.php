<?php
namespace App\Services;

class ConfigManager
{
    /** @var array */
    private array $servicesMatrix = [];

    /** @var array */
    private array $stateConfig = [];

    /** @var array<mixed> */
    private array $systemDrivers;

    public function __construct()
    {
        $basePath = dirname(__DIR__, 2);

        // Load Services Config
        $servicesPath = $_ENV["SERVICES_CONFIG"] ?? getenv("SERVICES_CONFIG");
        if ($servicesPath) {
            $fullServicesPath = $basePath . "/" . ltrim($servicesPath, "/");
            if (file_exists($fullServicesPath)) {
                $this->servicesMatrix =
                    json_decode(file_get_contents($fullServicesPath), true) ?:
                    [];
            }
        }

        // Load State Config
        $statePath = $_ENV["STATE_CONFIG"] ?? getenv("STATE_CONFIG");
        if ($statePath) {
            $fullStatePath = $basePath . "/" . ltrim($statePath, "/");
            if (file_exists($fullStatePath)) {
                $this->stateConfig =
                    json_decode(file_get_contents($fullStatePath), true) ?: [];
            }
        }

        $this->buildSystemDriverRegistry();
    }

    /**
     * Builds the complete array of system drivers currently offered to the application.
     * Maps every active category type straight to its active ID and operational configuration.
     *
     */
    private function buildSystemDriverRegistry(): void
    {
        $logPath = dirname(__DIR__, 2) . "/.config/error.log";
        @file_put_contents(
            $logPath,
            "[Registry Loop] Starting buildSystemDriverRegistry()...\n",
            FILE_APPEND,
        );
        @file_put_contents(
            $logPath,
            "[Registry Loop] Content of stateConfig:\n" .
                print_r($this->stateConfig, true) .
                "\n",
            FILE_APPEND,
        );

        $systemDrivers = [];

        // Loop through everything your state file has activated
        foreach ($this->stateConfig as $category => $activeServiceId) {
            $typeInfo = gettype($activeServiceId);
            @file_put_contents(
                $logPath,
                "[Registry Loop] Processing Category: '{$category}' | Value Type: {$typeInfo} | Value: " .
                    (is_string($activeServiceId)
                        ? "'{$activeServiceId}'"
                        : "ARRAY") .
                    "\n",
                FILE_APPEND,
            );

            if (is_string($activeServiceId)) {
                $meta =
                    $this->resolveServiceMeta($category, $activeServiceId) ?:
                    [];

                @file_put_contents(
                    $logPath,
                    sprintf(
                        "[Registry Loop] resolveServiceMeta Output for ('%s', '%s'):\n%s\n",
                        $category,
                        $activeServiceId,
                        print_r($meta, true),
                    ),
                    FILE_APPEND,
                );

                // Bundle the core properties into a standardized model object
                $systemDrivers[$category] = [
                    "id" => $activeServiceId,
                    "endpoint" => $meta["endpoint"] ?? "",
                    "type" => $meta["type"] ?? "shell",
                    "method" => $meta["method"] ?? "POST",
                    "action" => $meta["action"] ?? "",
                    "cmds" => $meta["cmds"] ?? [],
                ];

                @file_put_contents(
                    $logPath,
                    "[Registry Loop] Successfully bundled '{$category}' into registry.\n",
                    FILE_APPEND,
                );
            } else {
                @file_put_contents(
                    $logPath,
                    "[Registry Loop] WARNING: Skipped '{$category}' because its value is not a string!\n",
                    FILE_APPEND,
                );
            }
        }

        $this->systemDrivers = $systemDrivers;

        @file_put_contents(
            $logPath,
            "[Registry Loop] Final compiled systemDrivers Output:\n" .
                print_r($this->systemDrivers, true) .
                "\n",
            FILE_APPEND,
        );
    }

    /**
     *
     * Get the system drivers registry. This is an array of service
     * configurations that are currently active in the state file.
     *
     * @return array<mixed>
     */
    public function getSystem()
    {
        return $this->systemDrivers;
    }

    /**
     * Get the entire services configuration matrix.
     */
    public function getServicesConfig(): array
    {
        return $this->servicesMatrix;
    }

    /**
     * Get the entire state configuration matrix.
     */
    public function getStateConfig(): array
    {
        return $this->stateConfig;
    }

    /**
     * Resolves metadata for a specific key/service pairing.
     */
    public function resolveServiceMeta(
        string $category,
        string $serviceId,
    ): ?array {
        return $this->servicesMatrix[$category][$serviceId] ?? null;
    }

    /**
     * Get services matching a specific category block.
     * @return array<string, mixed>
     */
    public function getServicesByCategory(string $category): array
    {
        return $this->servicesMatrix[$category] ?? [];
    }

    /**
     * Get a specific metadata array for a given service.
     */
    public function getServiceMeta(string $category, string $serviceId): ?array
    {
        return $this->servicesMatrix[$category][$serviceId] ?? null;
    }

    /**
     * Get a specific value from the state configuration using a key.
     */
    public function getStateValue(string $key, mixed $default = null): mixed
    {
        return $this->stateConfig[$key] ?? $default;
    }

    /**
     * Converts the current configuration state into a pretty-printed JSON string for logging.
     */
    public function __toString(): string
    {
        return (string) json_encode(
            [
                "services_matrix" => $this->servicesMatrix,
                "state_config" => $this->stateConfig,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
    }
}
