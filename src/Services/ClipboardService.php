<?php

namespace App\Services;

/**
 * ClipboardService provides a method to read text from the system
 * clipboard across different OS environments safely.
 * @package App\Services
 *
 */
class ClipboardService
{
    /**
     * Attempts to read text from the system clipboard across different
     * OS environments safely.
     *
     * @throws \Exception If the clipboard operation fails.
     *
     * @return string The text read from the clipboard or an empty string.
     */
    public static function read(): string
    {
        $os = PHP_OS_FAMILY;

        if ($os === "Darwin") {
            return (string) shell_exec("pbpaste 2>/dev/null");
        }

        if ($os === "Windows") {
            return (string) shell_exec(
                'powershell.exe -command "Get-Clipboard" 2>NUL',
            );
        }

        $hasDisplay = getenv("DISPLAY") || getenv("WAYLAND_DISPLAY");

        if (!$hasDisplay) {
            return "";
        }

        if (trim((string) shell_exec("which wl-paste 2>/dev/null"))) {
            return (string) shell_exec("timeout 1 wl-paste 2>/dev/null");
        }

        if (trim((string) shell_exec("which xclip 2>/dev/null"))) {
            return (string) shell_exec(
                "timeout 1 xclip -selection clipboard -o 2>/dev/null",
            );
        }

        if (trim((string) shell_exec("which xsel 2>/dev/null"))) {
            return (string) shell_exec("timeout 1 xsel -b -o 2>/dev/null");
        }

        return "";
    }
}
