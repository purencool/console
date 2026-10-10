<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set("display_errors", "1");

require __DIR__ . "/../vendor/autoload.php";

use Dotenv\Dotenv;
use App\Application;

function env_require(string $key): string
{
    if (!isset($_ENV[$key]) || trim($_ENV[$key]) === "") {
        throw new \RuntimeException("Missing required .env variable: '{$key}'");
    }
    return $_ENV[$key];
}

function scaffoldConfigurationDirectory(
    string $source,
    string $destination,
): void {
    if (is_dir($destination)) {
        return;
    }

    if (!is_dir($source)) {
        throw new \RuntimeException(
            "Source configuration blueprint directory missing: '{$source}'",
        );
    }

    if (!@mkdir($destination, 0755, true) && !is_dir($destination)) {
        throw new \RuntimeException(
            "Failed to create runtime folder: '{$destination}'",
        );
    }

    foreach (glob($source . "/*") as $file) {
        if (is_file($file)) {
            @copy($file, $destination . "/" . basename($file));
        }
    }
}

// Load Environment Variables
$envPath = __DIR__ . "/../.env";
if (!file_exists($envPath)) {
    throw new \RuntimeException("Required .env file is missing at: {$envPath}");
}
Dotenv::createImmutable(__DIR__ . "/..")->load();

scaffoldConfigurationDirectory(__DIR__ . "/../config", __DIR__ . "/../.config");

$logFile = dirname(__DIR__) . "/.config/error.log";

$terminalCrashHandler = function (\Throwable $e) use ($logFile) {
    // Escape alternate buffers and reset native terminal parameters instantly
    echo "\x1b[?1049l\x1b[?25h\x1b[0m";

    if (function_exists("shell_exec")) {
        @shell_exec("stty sane");
        @shell_exec("clear");
    }

    echo "\n\x1b[1;31m=== APPLICATION CRASH DETECTED ===\x1b[0m\n";

    $errorMessage = sprintf(
        "[%s] %s: %s\nFile: %s\nLine: %d\n",
        date("Y-m-d H:i:s"),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
    );
    $trace = $e->getTraceAsString();

    // Output to screen and log file atomically
    echo $errorMessage . "\n" . $trace . "\n\n";
    @file_put_contents(
        $logFile,
        $errorMessage . "\n" . $trace . "\n\n",
        FILE_APPEND,
    );

    exit(1);
};

// Set Error & Exception Handlers
set_error_handler(function ($errno, $errstr, $errfile, $errline) use (
    $terminalCrashHandler,
) {
    if (
        !(error_reporting() & $errno) ||
        $errno === E_DEPRECATED ||
        $errno === E_USER_DEPRECATED
    ) {
        return false;
    }
    $terminalCrashHandler(
        new \ErrorException($errstr, 0, $errno, $errfile, $errline),
    );
    return true;
});
set_exception_handler($terminalCrashHandler);

try {
    $config = new \App\Services\ConfigManager();
    // Map Shared Services Array Container
    $services = [
        "config" => $config,
        "log" => new \App\Services\LogService(),
        "keybindings" => new \App\Services\KeyBindingService(),
        "json" => new \App\Services\JsonService(),
        "clipboard" => new \App\Services\ClipboardService(),
        "scanner" => new \App\Services\DirectoryScannerService(),
        "shell" => new \App\Services\ShellService(),
        "snippets" => new \App\Services\SnippetService(),
        "layout" => new \App\Services\TerminalLayoutService(),
        "llm" => new \App\Services\LlmService(),
        "state" => new \App\State\AppState($config),
    ];

    $app = new Application($services);
    $app->run();
} catch (\Throwable $fatalRuntimeError) {
    $terminalCrashHandler($fatalRuntimeError);
}
