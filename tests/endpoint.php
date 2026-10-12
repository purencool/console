<?php
/**
 * Artisan Shim Endpoint
 * Uniform JSON payload processing block across both CLI and HTTP boundaries.
 */

header("Content-Type: application/json");

$requestBody = "";
$invocationMethod = PHP_SAPI === "cli" ? "CLI_ARGUMENT" : "HTTP_STREAM";

// 1. Standarised Ingestion: Read uniform JSON strings from CLI arguments or HTTP posts dynamically
if (PHP_SAPI === "cli") {
    $requestBody = $argv[1] ?? "{}";
} else {
    $requestBody = file_get_contents("php://input");
}

// =========================================================================
// INBOUND CONTEXT LOGGING ENTRYPOINT
// =========================================================================
$basePath = dirname(__DIR__);
$logDirectory = $basePath . "/.config";
if (!file_exists($logDirectory)) {
    @mkdir($logDirectory, 0755, true);
}
$logFilePath = $logDirectory . "/endpoint_activity.log";
$timestamp = date("Y-m-d H:i:s");

@file_put_contents(
    $logFilePath,
    "========================================================================\n" .
        "[{$timestamp}] [INBOUND] Request received via context channel: {$invocationMethod}\n" .
        "📥 RAW TRANSMISSION STRING POOL:\n" .
        $requestBody .
        "\n",
    FILE_APPEND,
);
// =========================================================================

// Exactly identical JSON processing pipeline across both execution contexts
$data = json_decode($requestBody, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    if (PHP_SAPI !== "cli") {
        http_response_code(400);
    }

    $errorResponse = json_encode(
        [
            "status" => "error",
            "message" => "Invalid JSON payload received.",
            "raw_received" => $requestBody,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    );

    @file_put_contents(
        $logFilePath,
        "❌ [PARSING ERROR] Malformed JSON syntax structure caught. Aborting.\n" .
            "📤 OUTBOUND ERROR RESPONSE STRING:\n" .
            $errorResponse .
            "\n" .
            "========================================================================\n",
        FILE_APPEND,
    );

    echo $errorResponse;
    exit();
}

// Identify parameters from the standardized data layout map
$action = $data["action"] ?? null;
$inputParam = $data["input"] ?? "";

// Path to your artisan executable relative to this file
$artisanPath = __DIR__ . "/../../agents/artisan";

// Map incoming actions to strict Artisan commands (Prevents shell injection)
switch ($action) {
    case "llm_chat":
        $escapedInput = escapeshellarg($inputParam);
        $command =
            "php " .
            escapeshellarg($artisanPath) .
            " pnc:agent:chat --input=" .
            $escapedInput;
        break;

    case "skills":
        $command =
            "php " .
            escapeshellarg($artisanPath) .
            " pnc:agent:skills --pretty";
        break;

    case "cache_clear":
        $command = "php " . escapeshellarg($artisanPath) . " cache:clear";
        break;

    case "migrate":
        $command = "php " . escapeshellarg($artisanPath) . " migrate";
        break;

    case "migrate_dry":
        $command = "php " . escapeshellarg($artisanPath) . " migrate --pretend";
        break;

    case "list":
    default:
        $command = "php " . escapeshellarg($artisanPath) . " list";
        break;
}

// Log internal routing task assignment frames
@file_put_contents(
    $logFilePath,
    "🎯 EXTRACTED PROPERTIES:\n" .
        "   Category Action -> '" .
        ($action ?? "none") .
        "'\n" .
        "   Input Query     -> '" .
        $inputParam .
        "'\n" .
        "SUBSHELL EXECUTION INVOCATION:\n" .
        "   " .
        $command .
        "\n",
    FILE_APPEND,
);

// Execute the command safely and capture output
$output = [];
$returnCode = 0;
exec($command . " 2>&1", $output, $returnCode);

// Respond with a clean JSON object structure envelope
if ($returnCode === 0) {
    $finalResponse = json_encode(
        [
            "status" => "success",
            "command_executed" => $action ?? "list",
            "output" => $output,
        ],
        JSON_UNESCAPED_SLASHES,
    );
} else {
    if (PHP_SAPI !== "cli") {
        http_response_code(500);
    }
    $finalResponse = json_encode(
        [
            "status" => "error",
            "command_executed" => $action ?? "list",
            "return_code" => $returnCode,
            "output" => $output,
        ],
        JSON_UNESCAPED_SLASHES,
    );
}

// =========================================================================
// OUTBOUND RESPONSE AUDIT LOG CLOSURE
// =========================================================================
@file_put_contents(
    $logFilePath,
    "📦 SUBSHELL EXIT CODE: " .
        $returnCode .
        "\n" .
        "📤 OUTBOUND SYSTEM CONSOLE PACKET RESPONSE:\n" .
        json_encode(json_decode($finalResponse), JSON_PRETTY_PRINT) .
        "\n" .
        "========================================================================\n",
    FILE_APPEND,
);
// =========================================================================

echo $finalResponse;
