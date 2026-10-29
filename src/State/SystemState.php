<?php
namespace App\State;

use App\Services\ConfigManager;

class SystemState
{
    private bool $running = true;
    private string $inputFocus = "chat";
    private bool $sidebarVisible = true;
    private ?string $scannedPath = null;
    private bool $isProcessing = false;
    private float $processingStartTime = 0.0;
    private array $inputHistory = [];
    private int $historyIndex = -1;
    private string $tempInput = "";
    private array $systemDrivers = [];
    private array $options = [];

    // 💡 Add these two new properties to track matches
    private array $filteredMatches = [];
    private string $searchPrefix = "";

    /**
     * Initializes the system profiles and ingests registries from the centralized config manager.
     */
    public function __construct(ConfigManager $config)
    {
        $this->systemDrivers = $config->getSystem();
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function getInputFocus(): string
    {
        return $this->inputFocus;
    }

    public function setInputFocus(string $focus): void
    {
        $this->inputFocus = $focus;
    }

    public function toggleInputFocus(): void
    {
        $this->inputFocus = $this->inputFocus === "chat" ? "terminal" : "chat";
    }

    public function isSidebarVisible(): bool
    {
        return $this->sidebarVisible;
    }

    public function toggleSidebar(): void
    {
        $this->sidebarVisible = !$this->sidebarVisible;
    }

    public function getScannedPath(): ?string
    {
        return $this->scannedPath;
    }

    public function setScannedPath(?string $path): void
    {
        $this->scannedPath = $path;
    }

    public function isProcessing(): bool
    {
        return $this->isProcessing;
    }

    public function setProcessing(bool $processing): void
    {
        $this->isProcessing = $processing;
        if ($processing) {
            $this->processingStartTime = microtime(true);
        }
    }

    public function getProcessingDuration(): float
    {
        return round(microtime(true) - $this->processingStartTime, 2);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function toggleOption(string $key): void
    {
        if (isset($this->options[$key])) {
            $this->options[$key]["checked"] = !$this->options[$key]["checked"];
        }
    }

    public function getSystemDrivers(): array
    {
        return $this->systemDrivers;
    }

    public function addHistory(string $input): void
    {
        $this->inputHistory[] = $input;
        $this->clearHistorySearch(); // Clear caches when a new command is run
    }

    // 💡 Helper method to reset our filter caches when typing normally
    public function clearHistorySearch(): void
    {
        $this->historyIndex = -1;
        $this->filteredMatches = [];
        $this->searchPrefix = "";
    }

    /**
     * Cycles chat prompt input history retrieval collections backward (Up).
     * Now matches entries starting with or containing your typed string!
     */
    public function historyUp(
        callable $setInputTextCallback,
        callable $getInputTextCallback,
    ): void {
        if (empty($this->inputHistory)) {
            return;
        }

        // First time hitting UP: analyze what the user typed and create a filtered subset
        if ($this->historyIndex === -1) {
            $this->tempInput = $getInputTextCallback();
            $this->searchPrefix = trim($this->tempInput);

            if ($this->searchPrefix !== "") {
                // Find past commands that contain or start with the typed filter (case-insensitive)
                $prefix = strtolower($this->searchPrefix);
                $this->filteredMatches = array_values(
                    array_filter($this->inputHistory, function ($cmd) use (
                        $prefix,
                    ) {
                        return str_contains(strtolower($cmd), $prefix);
                    }),
                );
            } else {
                // If the box was completely empty, navigate all history
                $this->filteredMatches = $this->inputHistory;
            }
        }

        // Navigate backwards through our specific subset of matches
        if (
            !empty($this->filteredMatches) &&
            $this->historyIndex < count($this->filteredMatches) - 1
        ) {
            $this->historyIndex++;
            // Pull items from newest to oldest within our filtered array
            $historicalRecord =
                $this->filteredMatches[
                    count($this->filteredMatches) - 1 - $this->historyIndex
                ];
            $setInputTextCallback($historicalRecord);
        }
    }

    /**
     * Cycles chat prompt input history retrieval collections forward (Down).
     */
    public function historyDown(callable $setInputTextCallback): void
    {
        if ($this->historyIndex === -1) {
            return;
        }

        $this->historyIndex--;

        if ($this->historyIndex === -1) {
            // Reached the bottom: put back exactly what they were typing originally
            $setInputTextCallback($this->tempInput);
            $this->filteredMatches = [];
            $this->searchPrefix = "";
        } else {
            $historicalRecord =
                $this->filteredMatches[
                    count($this->filteredMatches) - 1 - $this->historyIndex
                ];
            $setInputTextCallback($historicalRecord);
        }
    }
}
