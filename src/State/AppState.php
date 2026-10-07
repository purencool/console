<?php
namespace App\State;

use ValueError;

/**
 * AppState class represents the state of the application.
 * It contains various properties and methods to manage the
 * state of the application.
 *
 * Class AppState
 * @package App\State
 */
class AppState
{
    /** @var bool */
    private bool $running = true;

    /** @var string */
    private string $inputText = "";

    /** @var string[] */
    private array $chatLog = [
        "[System] Welcome to the Console! Type a path (e.g. /path/to/dir) or prompt. Ctrl+S: sidebar, Ctrl+T: terminal.",
    ];

    /** @var int */
    private int $scrollOffset = 0;

    /** @var bool */
    private bool $autoScroll = true;

    /** @var bool */
    private bool $terminalActive = false;

    /** @var string */
    private string $terminalPrompt = ">";

    /** @var int */
    private int $terminalWidth = 80;

    /** @var int */
    private int $terminalHeight = 30;

    /** @var bool */
    private bool $sidebarDrawerOpen = false;

    /** @var bool */
    private bool $mouseCaptureActive = true;

    /** @var bool */
    private bool $terminalDrawerOpen = false;

    /** @var string */
    private string $inputFocus = "chat";

    /** @var string */
    private string $activeTerminalInput = "";

    /** @var string */
    private string $activeTerminalOutput = "";

    /** @var array */
    private array $terminalBuffer = [];

    /** @var int */
    private int $terminalScrollOffset = 0;

    /** @var bool */
    private bool $sidebarVisible = true;

    /** @var ?string */
    private ?string $scannedPath = null;

    /** @var string */
    private string $scannedContext = "";

    /** @var array */
    private array $options = [
        "cache" => ["label" => "Clear Cache", "checked" => false],
        "migrate" => ["label" => "Run Migrations", "checked" => true],
        "dryrun" => ["label" => "Dry Run Mode", "checked" => false],
    ];

    /**
     * IsRunning getter that returns the value of the isRunning property.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Stop method that sets the value of the running property to false.
     *
     * @return void
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * GetInputText getter that returns the value of the inputText property.
     *
     * @return string
     */
    public function getInputText(): string
    {
        return $this->inputText;
    }

    /**
     * SetInputText setter that sets the value of the inputText property.
     *
     * @param string $text
     * @return void
     */
    public function setInputText(string $text): void
    {
        $this->inputText = $text;
    }

    /**
     * AppendInputText method that appends a character to the inputText property.
     *
     * @param string $char
     * @return void
     */
    public function appendInputText(string $char): void
    {
        $this->inputText .= $char;
    }

    /**
     * GetChatLog method that returns the chat log as an array.
     *
     * @return array<string>
     *   Returns the chat log as an array.
     *
     */
    public function getChatLog(): array
    {
        return $this->chatLog;
    }

    /**
     * AddChatMessage method that adds a message to the chat log and ensures
     * it does not exceed 1000 messages. If it does, it removes the oldest
     * messages.
     *
     * @param string $msg The message to add to the chat log.
     * @return void
     *
     */
    public function addChatMessage(string $msg): void
    {
        $this->chatLog[] = $msg;
        if (count($this->chatLog) > 1000) {
            $this->chatLog = array_slice($this->chatLog, -1000);
        }
    }

    /**
     * GetScrollOffset method that returns the current scroll offset.
     *
     * @return int
     *    Returns The current scroll offset.
     *
     */
    public function getScrollOffset(): int
    {
        return $this->scrollOffset;
    }

    /**
     * SetScrollOffset method that sets the scroll offset. It ensures
     * the offset is not negative.
     *
     * @param int $offset The new scroll offset.
     * @return void
     *
     * @throws ValueError
     */
    public function setScrollOffset(int $offset): void
    {
        $this->scrollOffset = max(0, $offset);
    }

    /**
     * GetAutoScroll method that returns the current auto scroll setting.
     *
     * @return bool
     *    Returns The current auto scroll setting.
     *
     */
    public function isAutoScroll(): bool
    {
        return $this->autoScroll;
    }

    /**
     * SetAutoScroll method that sets the auto scroll setting. It ensures
     * the setting is either true or false.
     *
     * @param bool $auto The new auto scroll setting.
     * @return void
     *
     * @throws ValueError
     */
    public function setAutoScroll(bool $auto): void
    {
        $this->autoScroll = $auto;
    }

    /**
     * IsMouseCaptureActive method that returns the current mouse capture setting.
     *
     * @return bool
     *    Returns The current mouse capture setting.
     */
    public function isMouseCaptureActive(): bool
    {
        return $this->mouseCaptureActive;
    }

    /**
     * ToggleMouseCapture method that toggles the mouse capture setting.
     *
     * @return void
     */
    public function toggleMouseCapture(): void
    {
        $this->mouseCaptureActive = !$this->mouseCaptureActive;
    }

    /**
     * IsTerminalDrawerOpen method that returns the current terminal drawer setting.
     *
     * @return bool
     */
    public function isTerminalDrawerOpen(): bool
    {
        return $this->terminalDrawerOpen;
    }

    /**
     * ToggleTerminalDrawer method that toggles the terminal drawer setting.
     *
     * @return void
     */
    public function toggleTerminalDrawer(): void
    {
        $this->terminalDrawerOpen = !$this->terminalDrawerOpen;
    }

    /**
     * GetInputFocus method that returns the current input focus setting.
     *
     * @return string
     */
    public function getInputFocus(): string
    {
        return $this->inputFocus;
    }

    /**
     * SetInputFocus method that sets the input focus setting.
     *
     * @param string $focus
     */
    public function setInputFocus(string $focus): void
    {
        $this->inputFocus = $focus;
    }

    /**
     * ToggleInputFocus method that toggles the input focus setting.
     *
     * @return void
     */
    public function toggleInputFocus(): void
    {
        $this->inputFocus = $this->inputFocus === "chat" ? "terminal" : "chat";
    }

    /**
     * GetActiveTerminalInput method that returns the current active terminal input.
     *
     * @return string
     */
    public function getActiveTerminalInput(): string
    {
        return $this->activeTerminalInput;
    }

    /**
     * SetActiveTerminalInput method that sets the current active terminal input.
     *
     * @param string $input
     */
    public function setActiveTerminalInput(string $input): void
    {
        $this->activeTerminalInput = $input;
    }

    /**
     * AppendActiveTerminalInput method that appends a character to the active terminal input.
     *
     * @param string $char
     */
    public function appendActiveTerminalInput(string $char): void
    {
        $this->activeTerminalInput .= $char;
    }

    /**
     * GetTerminalBuffer method that returns the terminal buffer.
     *
     * @return array<mixed>
     */
    public function getTerminalBuffer(): array
    {
        return $this->terminalBuffer;
    }

    /**
     * AddTerminalOutput method that adds a line to the terminal buffer.
     *
     * @param string $line
     */
    public function addTerminalOutput(string $line): void
    {
        $this->terminalBuffer[] = $line;
    }

    /**
     * GetTerminalScrollOffset method that returns the terminal scroll offset.
     *
     * @return int
     */
    public function getTerminalScrollOffset(): int
    {
        return $this->terminalScrollOffset;
    }

    /**
     * SetTerminalScrollOffset method that sets the terminal scroll offset.
     *
     * @param int $offset
     * @throws ValueError if the offset is negative.
     *
     * @throws ValueError
     */
    public function setTerminalScrollOffset(int $offset): void
    {
        $this->terminalScrollOffset = max(0, $offset);
    }

    /**
     * IsSidebarVisible method that returns whether the sidebar is visible.
     *
     * @return bool
     */
    public function isSidebarVisible(): bool
    {
        return $this->sidebarVisible;
    }

    /**
     * ToggleSidebar method that toggles the visibility of the sidebar.
     *
     * @throws ValueError if the offset is negative.
     *
     * @throws ValueError
     */
    public function toggleSidebar(): void
    {
        $this->sidebarVisible = !$this->sidebarVisible;
    }

    /**
     * GetScannedPath method that returns the scanned path.
     *
     * @return string|null
     */
    public function getScannedPath(): ?string
    {
        return $this->scannedPath;
    }

    /**
     * SetScannedPath method that sets the scanned path.
     *
     * @throws ValueError if the offset is negative.
     *
     * @throws ValueError
     */
    public function setScannedPath(?string $path): void
    {
        $this->scannedPath = $path;
    }

    /**
     * GetScannedContext method that returns the scanned context.
     *
     * @return string|null
     */
    public function getScannedContext(): string
    {
        return $this->scannedContext;
    }

    /**
     * SetScannedContext method that sets the scanned context.
     *
     * @throws ValueError if the offset is negative.
     *
     * @throws ValueError
     */
    public function setScannedContext(string $context): void
    {
        $this->scannedContext = $context;
    }

    /**
     * GetOptions method that returns the options.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * ToggleOption method that toggles an option.
     *
     * @param string $key The key of the option to toggle.
     *
     * @throws ValueError if the offset is negative.
     *
     * @throws ValueError
     */
    public function toggleOption(string $key): void
    {
        if (isset($this->options[$key])) {
            $this->options[$key]["checked"] = !$this->options[$key]["checked"];
        }
    }
}
