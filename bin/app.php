<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set("display_errors", "1");

require __DIR__ . "/../vendor/autoload.php";

use Dotenv\Dotenv;
use App\Services\LlmService;
use App\Services\LogService;
use App\Application;

function env_require(string $key): string
{
    if (!isset($_ENV[$key]) || trim($_ENV[$key]) === "") {
        throw new \RuntimeException("Missing required .env variable: '{$key}'");
    }
    return $_ENV[$key];
}

$envPath = __DIR__ . "/../.env";
if (!file_exists($envPath)) {
    throw new \RuntimeException("Required .env file is missing at: {$envPath}");
}
$dotenv = Dotenv::createImmutable(__DIR__ . "/..");
$dotenv->load();

/**
 * Config
 */
$sourceDir = __DIR__ . "/../config";
$destinationDir = __DIR__ . "/../.config";
if (!is_dir($destinationDir)) {
    if (is_dir($sourceDir)) {
        mkdir($destinationDir, 0755, true);
        $files = glob($sourceDir . "/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                $targetFile = $destinationDir . "/" . basename($file);
                copy($file, $targetFile);
            }
        }
        echo "All files installed successfully.";
    } else {
        echo "Source directory does not exist.";
    }
}

/*
 * The absolute foolproof crash renderer
 */
$logFile = dirname(__DIR__) . "./.config/error.log";
$terminalCrashHandler = function (\Throwable $e) use ($logFile) {
    // 1. Force the terminal out of the alternate screen buffer and reset attributes
    echo "\x1b[?1049l"; // Exit alternate screen
    echo "\x1b[?25h"; // Show cursor
    echo "\x1b[0m"; // Reset text formatting styles

    if (function_exists("shell_exec")) {
        shell_exec("stty sane");
        shell_exec("clear"); // Clean up terminal rendering artifacts
    }

    echo "\n\x1b[1;31m=== APPLICATION CRASH DETECTED ===\x1b[0m\n";

    $timestamp = date("Y-m-d H:m:s");
    $errorMessage = sprintf(
        "[%s] %s: %s\nFile: %s\nLine: %d\n",
        $timestamp,
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
    );
    $trace = $e->getTraceAsString();

    // Visible standard output directly to your prompt screen
    echo $errorMessage . "\n" . $trace . "\n\n";

    // Robust atomic log file writing
    @file_put_contents(
        $logFile,
        $errorMessage . "\n" . $trace . "\n\n",
        FILE_APPEND,
    );

    exit(1);
};

// Intercept low-level standard PHP errors/warnings and convert to exceptions
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

// 🔥 CRITICAL: Capture standard uncaught exceptions globally
set_exception_handler($terminalCrashHandler);

// 🔥 THE BULLETPROOF JACKET: Ensure runtime setup can't exit raw mode silently
try {
    $logService = new LogService();
    $app = new Application($logService);
    $app->run();
} catch (\Throwable $fatalRuntimeError) {
    // If anything breaks inside the application run loop, intercept it here manually
    $terminalCrashHandler($fatalRuntimeError);
}
