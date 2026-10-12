<?php
/**
 * Artisan Shim Endpoint
 * Decodes incoming JSON requests, executes the corresponding Artisan command safely,
 * and returns the output as a JSON response.
 */

header("Content-Type: application/json");

//Capture and decode the incoming JSON payload
$requestBody = file_get_contents("php://input");
$data = json_decode($requestBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid JSON payload received.",
    ]);
    exit();
}

// Identify the requested command/action
$action = $data["action"] ?? null;
$inputParam = $data["input"] ?? "";

// Path to your artisan executable relative to this file
$artisanPath = __DIR__ . "/../../agents/artisan";

// Map incoming actions to strict Artisan commands (Prevents shell injection)
switch ($action) {
    case "llm_chat":
        // Escaping the user input safely for the CLI argument
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

// Execute the command safely and capture output
$output = [];
$returnCode = 0;

// exec handles array reference for line-by-line output
exec($command . " 2>&1", $output, $returnCode);

// Respond with a clean JSON object
if ($returnCode === 0) {
    echo json_encode([
        "status" => "success",
        "command_executed" => $action ?? "list",
        "output" => $output,
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "command_executed" => $action ?? "list",
        "return_code" => $returnCode,
        "output" => $output,
    ]);
}
