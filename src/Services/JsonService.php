<?php
namespace App\Services;

use App\State\AppState;
use Exception;

class JsonService
{
    /**
     * Executes an infrastructure adapter routing task using pre-compiled AppState system drivers.
     *
     * @param array $services The global application state object container
     * @param string $category The target system module category context (e.g., 'ai', 'skills', 'commands')
     * @param string $input Explicit text parameters or user strings bound to the context query
     * @return array Standardised unified format array output containing response metrics
     */
    public function makeCall(
        array $services,
        string $category,
        string $input = "",
    ): array {
        /** @var \App\Services\ConfigManager $config */
        $config = $services["config"] ?? null;

        $logPath = dirname(__DIR__, 2) . "/.config/error.log";

        if (!$config) {
            throw new \Exception(
                "Critical Core Failure: Centralised 'config' manager service is
                missing from container registration.",
            );
        }

        // ConfigManager service key!
        $driversMatrix = $config->getSystem();
        $driverProfile = $driversMatrix[$category] ?? null;

        if (!$driverProfile) {
            throw new \Exception(
                "Target system driver profile category context error: '{$category}'
                is not configured or active inside your active services.",
            );
        }

        // Instantly assign values from the pre-resolved structural object fields
        $endpoint = $driverProfile["endpoint"] ?? "";
        $method = strtoupper($driverProfile["method"] ?? "POST");
        $type = strtolower($driverProfile["type"] ?? "shell");
        $key = $driverProfile["key"] ?? "";
        $action = $driverProfile["action"] ?? "default";

        $payload = [
            "action" => $action,
            "input" => $input,
            "key" => $key,
        ];

        // Safely extract the profile matching your category context
        $driverProfile = $driversMatrix[$category] ?? null;

        // Fallback shim: check if it uses sub-nested arrays (e.g., $driversMatrix[$category]['default'])
        if (is_array($driverProfile) && !isset($driverProfile["endpoint"])) {
            $driverProfile =
                $driverProfile["default"] ??
                (array_values($driverProfile)[0] ?? null);
        }

        if (!$driverProfile) {
            throw new Exception(
                "Target system driver profile category context error: '{$category}' is not configured or active inside getSystemDrivers().",
            );
        }

        // Instantly assign values from the resolved structural array fields
        $endpoint = $driverProfile["endpoint"] ?? "";
        $method = strtoupper($driverProfile["method"] ?? "POST");
        $type = strtolower($driverProfile["type"] ?? "shell");
        $key = $driverProfile["key"] ?? "";
        $action = $driverProfile["action"] ?? "default";

        $payload = [
            "action" => $action,
            "input" => $input,
            "key" => $key,
        ];

        // Centralized Factory Routing Matrix Switch
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
     * Infrastructure Local CLI / Shell Processors
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

        // Convert the payload dictionary into a uniform JSON string format
        $jsonPayloadString = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // Safely wrap the complete JSON string into a single shell argument block
        $escapedJsonArgument = escapeshellarg($jsonPayloadString);

        // Compile the command layout matching your standardized convention
        $command = sprintf("%s %s 2>&1", $endpoint, $escapedJsonArgument);

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $rawOutput = trim(implode("\n", $output));
        $decoded = json_decode($rawOutput, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $isSuccess =
                $returnCode === 0 && ($decoded["status"] ?? "") !== "error";
            $outputLines =
                $decoded["output"] ?? ($decoded["message"] ?? $rawOutput);

            return [
                "success" => $isSuccess,
                "output" => is_array($outputLines)
                    ? $outputLines
                    : [$outputLines],
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
     * Remote Web Microservice APIs
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
