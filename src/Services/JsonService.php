<?php
namespace App\Services;

use Exception;

class JsonService
{
    /** @var array The parsed multidimensional configuration matrix */
    private array $config = [];

    /**
     * Instantiates the service and automatically parses the multi-line .env matrix.
     */
    public function __construct()
    {
        $this->loadConfigFromEnv();
    }

    /**
     * Extracts and validates the multi-line JSON environment payload.
     */
    private function loadConfigFromEnv(): void
    {
        $rawJson = $_ENV["SERVICES_CONFIG"] ?? getenv("SERVICES_CONFIG");

        if (!$rawJson) {
            $this->config = [];
            return;
        }

        $cleanJson = trim($rawJson);
        $parsed = json_decode($cleanJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log(
                "[JsonService Error] Failed to decode SERVICES_CONFIG: " .
                    json_last_error_msg(),
            );
            $this->config = [];
            return;
        }

        $this->config = $parsed;
    }

    /**
     * Safely retrieves specific configuration node properties using dot-notation.
     * Example: $service->get('ai.service_one.type');
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode(".", $key);
        $cursor = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !isset($cursor[$segment])) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * Executes a call by dynamically routing it through the correct infrastructure adapter (Shell vs HTTP).
     *
     * @param string $category E.g., 'ai', 'skills', or 'commands'
     * @param string $service E.g., 'service_one', 'skills_default'
     * @param string $action The structural identifier action keyword mapping (e.g., 'llm_chat', 'skills')
     * @param string $input Explicit text parameters or user strings bound to the context query
     * @return array Standardised unified format array output return response
     */
    public function makeCall(
        string $category,
        string $service,
        string $action,
        string $input = "",
    ): array {
        $serviceConfig = $this->get("{$category}.{$service}");

        if (!$serviceConfig) {
            throw new Exception(
                "Target configuration path error: '{$category}.{$service}' not found.",
            );
        }

        $endpoint = $serviceConfig["endpoint"] ?? "";
        $method = strtoupper($serviceConfig["method"] ?? "POST");
        $key = $serviceConfig["key"] ?? "";

        // 💡 Read the type explicitly from your custom configuration property fields!
        // Fallback to auto-detection rules if the property is absent in older environment configurations.
        $type = strtolower(
            $serviceConfig["type"] ?? $this->autoDetectType($endpoint),
        );

        $payload = [
            "action" => $action,
            "input" => $input,
            "key" => $key,
        ];

        // 🔀 Centralized Factory Routing Matrix Switch
        switch ($type) {
            case "shell":
                return $this->executeLocalShellCall($endpoint, $payload);

            case "http":
            case "https":
                return $this->executeRemoteHttpCall(
                    $endpoint,
                    $method,
                    $payload,
                );

            default:
                throw new Exception(
                    "Unsupported driver adapter target property conversion execution type: '{$type}'",
                );
        }
    }

    /**
     * Backwards compatibility fallback to infer type if missing from the specific node definition.
     */
    private function autoDetectType(string $endpoint): string
    {
        if (
            str_starts_with($endpoint, "http://") ||
            str_starts_with($endpoint, "https://")
        ) {
            return "http";
        }
        return "shell";
    }

    /**
     * Infrastructure Adapter A: Local CLI / Shell Processors
     */
    private function executeLocalShellCall(
        string $endpoint,
        array $payload,
    ): array {
        if (empty($endpoint)) {
            return [
                "success" => false,
                "output" => [
                    "Empty shell execution endpoint definition target.",
                ],
            ];
        }

        // Build CLI argument variables out of incoming context parameters securely
        $escapedAction = escapeshellarg($payload["action"]);
        $escapedInput = escapeshellarg($payload["input"]);
        $escapedKey = escapeshellarg($payload["key"]);

        // Format execution context lines directly mimicking target php execution shims
        $command = sprintf(
            "php %s --action=%s --input=%s --key=%s 2>&1",
            $endpoint,
            $escapedAction,
            $escapedInput,
            $escapedKey,
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        // Check if the script printed pre-formatted JSON directly
        $rawOutput = implode("\n", $output);
        $decoded = json_decode($rawOutput, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return [
                "success" =>
                    $returnCode === 0 && ($decoded["status"] ?? "") !== "error",
                "output" => $decoded["output"] ?? $output,
                "raw" => $rawOutput,
            ];
        }

        return [
            "success" => $returnCode === 0,
            "output" => $output,
            "raw" => $rawOutput,
        ];
    }

    /**
     * Infrastructure Adapter B: Remote Web Microservice APIs (Artisan Shim Web Server)
     */
    private function executeRemoteHttpCall(
        string $endpoint,
        string $method,
        array $payload,
    ): array {
        if (empty($endpoint)) {
            return [
                "success" => false,
                "output" => [
                    "Empty HTTP network service endpoint URI block description target.",
                ],
            ];
        }

        $jsonPayload = json_encode($payload);

        $options = [
            "http" => [
                "method" => $method,
                "header" =>
                    "Content-Type: application/json\r\n" .
                    "Accept: application/json\r\n" .
                    "Content-Length: " .
                    strlen($jsonPayload) .
                    "\r\n",
                "content" => $jsonPayload,
                "ignore_errors" => true,
            ],
        ];

        $context = stream_context_create($options);
        $responseBody = @file_get_contents($endpoint, false, $context);

        if ($responseBody === false) {
            return [
                "success" => false,
                "output" => [
                    "HTTP Network Communication Error: Connection string unreachable.",
                ],
                "raw" => "",
            ];
        }

        $decoded = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                "success" => false,
                "output" => [
                    "Malformed JSON content type syntax layout error caught in transmission stream.",
                ],
                "raw" => $responseBody,
            ];
        }

        $success = ($decoded["status"] ?? "") === "success";
        $outputLines = $decoded["output"] ?? [];

        return [
            "success" => $success,
            "output" => is_array($outputLines) ? $outputLines : [$outputLines],
            "raw" => $responseBody,
        ];
    }
}
