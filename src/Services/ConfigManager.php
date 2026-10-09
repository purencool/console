<?php
namespace App\Services;

class ConfigManager
{
    private array $servicesMatrix = [];

    public function __construct()
    {
        $rawJson = $_ENV['SERVICES_CONFIG'] ?? getenv('SERVICES_CONFIG') ?: '{}';
        $this->servicesMatrix = json_decode($rawJson, true) ?: [];
    }

    public function getServicesByCategory(string $category): array
    {
        return $this->servicesMatrix[$category] ?? [];
    }

    public function getServiceMeta(string $category, string $serviceId): ?array
    {
        return $this->servicesMatrix[$category][$serviceId] ?? null;
    }
}
