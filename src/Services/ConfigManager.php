<?php
namespace App\Services;

class ConfigManager
{
    /** @var array<string, array> */
    private array $servicesMatrix = [];

    /** @var array<string, mixed> */
    private array $stateConfig = [];

    /** @var array<string, array> */
    private array $systemDrivers = [];

    public function __construct()
    {
        $basePath = dirname(__DIR__, 2);

        // Ingest and parse Services Configuration Matrix
        $servicesPath = $_ENV["SERVICES_CONFIG"] ?? getenv("SERVICES_CONFIG");
        if ($servicesPath) {
            $fullServicesPath = $basePath . "/" . ltrim($servicesPath, "/");
            if (file_exists($fullServicesPath)) {
                $this->servicesMatrix =
                    json_decode(file_get_contents($fullServicesPath), true) ?:
                    [];
            }
        }

        // Ingest and parse State Activation Configuration
        $statePath = $_ENV["STATE_CONFIG"] ?? getenv("STATE_CONFIG");
        if ($statePath) {
            $fullStatePath = $basePath . "/" . ltrim($statePath, "/");
            if (file_exists($fullStatePath)) {
                $this->stateConfig =
                    json_decode(file_get_contents($fullStatePath), true) ?: [];
            }
        }

        // Compile active infrastructure mapping connections registry
        $this->buildSystemDriverRegistry();
    }

    /**
     * Compiles an optimized matrix of active system infrastructure driver profiles.
     */
    private function buildSystemDriverRegistry(): void
    {
        $systemDrivers = [];

        foreach ($this->stateConfig as $category => $activeServiceId) {
            if (is_string($activeServiceId)) {
                $meta =
                    $this->resolveServiceMeta($category, $activeServiceId) ?:
                    [];

                $systemDrivers[$category] = [
                    "id" => $activeServiceId,
                    "endpoint" => $meta["endpoint"] ?? "",
                    "type" => $meta["type"] ?? "shell",
                    "method" => $meta["method"] ?? "POST",
                    "action" => $meta["action"] ?? "",
                    "cmds" => $meta["cmds"] ?? [],
                ];
            }
        }

        $this->systemDrivers = $systemDrivers;
    }

    /**
     * Retrieves the compiled system drivers configuration registry.
     *
     * @return array<string, array>
     */
    public function getSystem(): array
    {
        return $this->systemDrivers;
    }

    /**
     * Retrieves the entire raw services configuration matrix.
     *
     * @return array<string, array>
     */
    public function getServicesConfig(): array
    {
        return $this->servicesMatrix;
    }

    /**
     * Retrieves the entire raw active state configuration matrix.
     *
     * @return array<string, mixed>
     */
    public function getStateConfig(): array
    {
        return $this->stateConfig;
    }

    /**
     * Resolves configuration metadata properties matching a specific category and service ID pairing.
     *
     * @return array<string, mixed>|null
     */
    public function resolveServiceMeta(
        string $category,
        string $serviceId,
    ): ?array {
        return $this->servicesMatrix[$category][$serviceId] ?? null;
    }

    /**
     * Retrieves an isolated profile bucket group matching a target category context.
     *
     * @return array<string, array>
     */
    public function getServicesByCategory(string $category): array
    {
        return $this->servicesMatrix[$category] ?? [];
    }

    /**
     * Retrieves metadata options for a given target service ID.
     *
     * @return array<string, mixed>|null
     */
    public function getServiceMeta(string $category, string $serviceId): ?array
    {
        return $this->servicesMatrix[$category][$serviceId] ?? null;
    }

    /**
     * Safely retrieves a single runtime config parameter out of the active state configurations map.
     */
    public function getStateValue(string $key, mixed $default = null): mixed
    {
        return $this->stateConfig[$key] ?? $default;
    }

    /**
     * Serializes active service configurations maps into a JSON representation string format.
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
