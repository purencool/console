<?php

declare(ticks=1);

$samples = [];
$samplingActive = true;
$startTime = microtime(true);

// High-speed execution stack trace sampler
register_tick_function(function () use (
    &$samples,
    &$samplingActive,
    $startTime,
) {
    if (!$samplingActive) {
        return;
    }

    // Auto-terminate and dump metrics after exactly 4 seconds
    if (microtime(true) - $startTime > 4.0) {
        $samplingActive = false;
        return;
    }

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

    // Capture the executing class and function context frame
    $currentFrame = $trace[1] ?? null;
    $callingFrame = $trace[2] ?? null;

    if ($currentFrame) {
        $class = $currentFrame["class"] ?? "Global";
        $function = $currentFrame["function"] ?? "main";
        $file = $currentFrame["file"] ?? "unknown";
        $line = $currentFrame["line"] ?? 0;

        $locationKey = sprintf(
            "%s->%s() called at %s:%d",
            $class,
            $function,
            basename($file),
            $line,
        );
        $samples[$locationKey] = ($samples[$locationKey] ?? 0) + 1;
    }
});

// Boot up your application entry registries manually
require_once __DIR__ . "/vendor/autoload.php";

$config = new \App\Services\ConfigManager();
$services = [
    "config" => $config,
    "state" => new \App\State\AppState($config),
    "json" => new \App\Services\JsonService(),
    "log" => new \App\Services\LogService(),
    "keybindings" => new \App\Services\KeyBindingService(),
    "clipboard" => new \App\Services\ClipboardService(),
    "scanner" => new \App\Services\DirectoryScannerService(),
    "shell" => new \App\Services\ShellService(),
    "snippets" => new \App\Services\SnippetService(),
    "layout" => new \App\Services\TerminalLayoutService(),
    "mouse" => new \App\Services\MouseInteractionService(),
];

echo "🚀 Starting 4-second internal user-land sampling pass...\n";

try {
    $app = new \App\Application($services);
    $app->run();
} catch (\Throwable $e) {
}

// =========================================================================
// OUTPUT METRICS EVALUATION DUMP
// =========================================================================
echo "\n USER-LAND PERFORMANCE DIAGNOSTIC REPORT\n";
echo "========================================================================\n";

if (empty($samples)) {
    echo "❌ Error: Zero execution samples captured. Ensure 'declare(ticks=1);' is supported.\n";
    exit(1);
}

arsort($samples);
$totalSamples = array_sum($samples);

$count = 0;
foreach ($samples as $methodSignature => $hits) {
    $percentage = ($hits / $totalSamples) * 100;
    printf(
        "[%02d] %5.1f%% (%d hits) -> %s\n",
        ++$count,
        $percentage,
        $hits,
        $methodSignature,
    );
    if ($count >= 8) {
        break;
    }
}
echo "========================================================================\n";
