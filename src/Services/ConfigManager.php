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
        $systemDrivers = [];

        // Loop through everything your state file has activated
        foreach ($this->stateConfig as $category => $activeServiceId) {
            // Filter out operational layout blocks like standard checkbox arrays
            if (is_string($activeServiceId)) {
                $meta =
                    $this->resolveServiceMeta($category, $activeServiceId) ?:
                    [];

                // Bundle the core properties into a standardized, easy-to-read model object
                $systemDrivers[$category] = [
                    "id" => $activeServiceId,
                    "endpoint" => $meta["endpoint"] ?? "",
                    "type" => $meta["type"] ?? "shell",
                    "method" => $meta["method"] ?? "POST",
                    "cmds" => $meta["cmds"] ?? [],
                ];
            }
        }

        $this->systemDrivers = $systemDrivers;
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
