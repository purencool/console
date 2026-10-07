<?php
error_reporting(E_ALL & ~E_DEPRECATED);
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\LlmService;
use App\Application;

function env_require(string $key): string {
    if (!isset($_ENV[$key]) || trim($_ENV[$key]) === '') {
        throw new \RuntimeException("Missing required .env variable: '{$key}'");
    }
    return $_ENV[$key];
}

$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    throw new \RuntimeException("Required .env file is missing at: {$envPath}");
}
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$llmChatCmd = env_require('LLM_CHAT_CMD');
$skillsCmd  = env_require('SKILLS_CMD');

$logFile = __DIR__ . '/../tui_error.log';

register_shutdown_function(function () {
    fwrite(STDOUT, "\x1b[?1049l");
    shell_exec('stty sane');
});

set_exception_handler(function (\Throwable $e) use ($logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $msg = "[$timestamp] EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    $msg .= $e->getTraceAsString() . "\n\n";
    file_put_contents($logFile, $msg, FILE_APPEND);
    exit(1); 
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($logFile) {
    if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) return false;
    $timestamp = date('Y-m-d H:i:s');
    $msg = "[$timestamp] ERROR [$errno]: $errstr in $errfile on line $errline\n";
    file_put_contents($logFile, $msg, FILE_APPEND);
    return false;
});

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () { exit(1); });
}

$llmService = new LlmService($llmChatCmd, $skillsCmd);
(new Application($llmService))->run();
