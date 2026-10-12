<?php
namespace App\Services;

class ClipboardService
{
    /** @var string|null Cached path or name of the resolved working system clipboard command utility */
    private ?string $detectedEngine = null;

    public function __construct()
    {
        // Check for working system clipboard binaries EXACTLY ONCE on boot!
        $this->detectClipboardEngine();
    }

    /**
     * Probes the operating system environment layers to discover and cache
     * a working clipboard binary tool.
     */
    private function detectClipboardEngine(): void
    {
        // Check for macOS native clipboard utilities first
        if (PHP_OS_FAMILY === "Darwin") {
            $this->detectedEngine = "pbpaste";
            return;
        }

        // Check Linux environment clipboard systems safely by testing paths once
        if (trim((string) @shell_exec("which wl-paste 2>/dev/null")) !== "") {
            $this->detectedEngine = "wl-paste";
            return;
        }

        if (trim((string) @shell_exec("which xclip 2>/dev/null")) !== "") {
            $this->detectedEngine = "xclip";
            return;
        }

        if (trim((string) @shell_exec("which xsel 2>/dev/null")) !== "") {
            $this->detectedEngine = "xsel";
            return;
        }

        $this->detectedEngine = "none";
    }

    /**
     * High-performance, cached clipboard text extractor.
     * Operates instantly without spawning redundant shell processes.
     */
    public function get(): string
    {
        // No shell processes or child forks are generated here when idle!
        switch ($this->detectedEngine) {
            case "pbpaste":
                return (string) @shell_exec("pbpaste 2>/dev/null");

            case "wl-paste":
                return (string) @shell_exec("timeout 1 wl-paste 2>/dev/null");

            case "xclip":
                return (string) @shell_exec(
                    "timeout 1 xclip -selection clipboard -o 2>/dev/null",
                );

            case "xsel":
                return (string) @shell_exec("timeout 1 xsel -b -o 2>/dev/null");

            default:
                return "";
        }
    }
}
