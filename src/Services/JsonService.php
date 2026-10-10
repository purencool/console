<?php
namespace App\Services;

use App\State\AppState;
use Exception;

class JsonService
{
    /**
     * Executes an infrastructure adapter routing task using pre-compiled AppState system drivers.
     *
     * @param AppState $state The global application state object container
     * @param string $category The target system module category context (e.g., 'ai', 'skills', 'commands')
     * @param string $input Explicit text parameters or user strings bound to the context query
     * @return array Standardised unified format array output containing response metrics
     */
    public function makeCall(
        AppState $state,
        string $category,
        string $input = "",
    ): array {
        print_r($state);
        exit();
        // Dynamically read the active configuration profile from the state machine's compiled registry
        $driverProfile = method_exists($state, "getDriverProfile")
            ? $state->getDriverProfile($category)
            : null;

        if (!$driverProfile) {
            throw new Exception(
                "Target system driver profile category context error: '{$category}' is not configured or active.",
            );
        }

        // Instantly assign values from the pre-resolved structural object fields

        $endpoint = $driverProfile["endpoint"] ?? "";
        $method = strtoupper($driverProfile["method"] ?? "POST");
        $type = strtolower($driverProfile["type"] ?? "shell");
        $key = $driverProfile["key"] ?? "";

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

        $escapedAction = escapeshellarg($payload["action"]);
        $escapedInput = escapeshellarg($payload["input"]);
        $escapedKey = escapeshellarg($payload["key"]);

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
     * Infrastructure Adapter B: Remote Web Microservice APIs (Shim Web Server)
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
