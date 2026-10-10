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
        $this->historyIndex = -1;
    }

    /**
     * Cycles chat prompt input history retrieval collections backward (Up).
     */
    public function historyUp(
        callable $setInputTextCallback,
        callable $getInputTextCallback,
    ): void {
        if (empty($this->inputHistory)) {
            return;
        }
        if ($this->historyIndex === -1) {
            $this->tempInput = $getInputTextCallback();
        }
        if ($this->historyIndex < count($this->inputHistory) - 1) {
            $this->historyIndex++;
            $historicalRecord =
                $this->inputHistory[
                    count($this->inputHistory) - 1 - $this->historyIndex
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
            $setInputTextCallback($this->tempInput);
        } else {
            $historicalRecord =
                $this->inputHistory[
                    count($this->inputHistory) - 1 - $this->historyIndex
                ];
            $setInputTextCallback($historicalRecord);
        }
    }
}
