<?php

namespace App\Services;

/**
 * Log servic
 * @package App\Services
 *
 */
class LogService
{
    /**
     *
     */
    public function log(string $message): void
    {
        $logFile = __DIR__ . "/../error.log";
        $timestamp = date("Y-m-d H:i:s");
        file_put_contents(
            $logFile,
            "[$timestamp] DEBUG: $message\n",
            FILE_APPEND,
        );
    }
}
