<?php
namespace App\State;

use ValueError;

class AppState
{
    private bool $running = true;
    private string $inputText = "";
    private int $inputCursor = 0;

    private array $chatLog = [
        "[System] Welcome to the Console! Type your prompt, or type / to see all available commands and shortcuts.",
    ];
    private int $scrollOffset = 0;
    private bool $autoScroll = true;
    private bool $terminalActive = false;
    private string $terminalPrompt = ">";
    private int $terminalWidth = 80;
    private int $terminalHeight = 30;
    private bool $sidebarDrawerOpen = false;
    private bool $mouseCaptureActive = true;
    private bool $terminalDrawerOpen = false;
    private string $inputFocus = "chat";

    private string $activeTerminalInput = "";
    private int $terminalCursor = 0;

    private string $activeTerminalOutput = "";
    private array $terminalBuffer = [];
    private int $terminalScrollOffset = 0;
    private bool $sidebarVisible = true;
    private ?string $scannedPath = null;
    private string $scannedContext = "";

    private bool $isProcessing = false;
    private float $processingStartTime = 0.0;

    private array $inputHistory = [];
    private int $historyIndex = -1;
    private string $tempInput = "";

    private array $loadedSystemSkills = [];
    private string $systemSkillContext = "";
    private array $loadedUserSkills = [];
    private string $userSkillContext = "";

    // --- FULL SCREEN EDITOR STATE ---
    private bool $editorOpen = false;
    private string $editorFilePath = "";
    private array $editorLines = [""];
    private int $editorRow = 0;
    private int $editorCol = 0;
    private string $editorStatus = "";
    private int $editorScrollY = 0;

    // --- EDITOR SELECTION & CLIPBOARD ---
    private bool $editorSelecting = false;
    private int $editorSelStartRow = 0;
    private int $editorSelStartCol = 0;
    private string $editorClipboard = "";

    private array $options = [
        "cache" => ["label" => "Clear Cache", "checked" => false],
        "migrate" => ["label" => "Run Migrations", "checked" => true],
        "dryrun" => ["label" => "Dry Run Mode", "checked" => false],
    ];

    public function isRunning(): bool
    {
        return $this->running;
    }
    public function stop(): void
    {
        $this->running = false;
    }

    public function getInputCursor(): int
    {
        return $this->inputCursor;
    }
    public function setInputCursor(int $pos): void
    {
        $this->inputCursor = max(0, min(mb_strlen($this->inputText), $pos));
    }
    public function getInputText(): string
    {
        return $this->inputText;
    }
    public function setInputText(string $text): void
    {
        $this->inputText = $text;
        $this->inputCursor = mb_strlen($text);
    }
    public function insertInputText(string $char): void
    {
        $left = mb_substr($this->inputText, 0, $this->inputCursor);
        $right = mb_substr($this->inputText, $this->inputCursor);
        $this->inputText = $left . $char . $right;
        $this->inputCursor += mb_strlen($char);
    }
    public function backspaceInputText(): void
    {
        if ($this->inputCursor > 0) {
            $left = mb_substr($this->inputText, 0, $this->inputCursor - 1);
            $right = mb_substr($this->inputText, $this->inputCursor);
            $this->inputText = $left . $right;
            $this->inputCursor--;
        }
    }

    public function getChatLog(): array
    {
        return $this->chatLog;
    }
    public function addChatMessage(string $msg): void
    {
        $this->chatLog[] = $msg;
        if (count($this->chatLog) > 1000) {
            $this->chatLog = array_slice($this->chatLog, -1000);
        }
    }

    public function getScrollOffset(): int
    {
        return $this->scrollOffset;
    }
    public function setScrollOffset(int $offset): void
    {
        $this->scrollOffset = max(0, $offset);
    }
    public function isAutoScroll(): bool
    {
        return $this->autoScroll;
    }
    public function setAutoScroll(bool $auto): void
    {
        $this->autoScroll = $auto;
    }
    public function isMouseCaptureActive(): bool
    {
        return $this->mouseCaptureActive;
    }
    public function toggleMouseCapture(): void
    {
        $this->mouseCaptureActive = !$this->mouseCaptureActive;
    }
    public function isTerminalDrawerOpen(): bool
    {
        return $this->terminalDrawerOpen;
    }
    public function toggleTerminalDrawer(): void
    {
        $this->terminalDrawerOpen = !$this->terminalDrawerOpen;
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

    public function getTerminalCursor(): int
    {
        return $this->terminalCursor;
    }
    public function setTerminalCursor(int $pos): void
    {
        $this->terminalCursor = max(
            0,
            min(mb_strlen($this->activeTerminalInput), $pos),
        );
    }
    public function getActiveTerminalInput(): string
    {
        return $this->activeTerminalInput;
    }
    public function setActiveTerminalInput(string $input): void
    {
        $this->activeTerminalInput = $input;
        $this->terminalCursor = mb_strlen($input);
    }
    public function insertActiveTerminalInput(string $char): void
    {
        $left = mb_substr($this->activeTerminalInput, 0, $this->terminalCursor);
        $right = mb_substr($this->activeTerminalInput, $this->terminalCursor);
        $this->activeTerminalInput = $left . $char . $right;
        $this->terminalCursor += mb_strlen($char);
    }
    public function backspaceActiveTerminalInput(): void
    {
        if ($this->terminalCursor > 0) {
            $left = mb_substr(
                $this->activeTerminalInput,
                0,
                $this->terminalCursor - 1,
            );
            $right = mb_substr(
                $this->activeTerminalInput,
                $this->terminalCursor,
            );
            $this->activeTerminalInput = $left . $right;
            $this->terminalCursor--;
        }
    }

    public function getTerminalBuffer(): array
    {
        return $this->terminalBuffer;
    }
    public function addTerminalOutput(string $line): void
    {
        $this->terminalBuffer[] = $line;
    }
    public function getTerminalScrollOffset(): int
    {
        return $this->terminalScrollOffset;
    }
    public function setTerminalScrollOffset(int $offset): void
    {
        $this->terminalScrollOffset = max(0, $offset);
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
    public function getScannedContext(): string
    {
        return $this->scannedContext;
    }
    public function setScannedContext(string $context): void
    {
        $this->scannedContext = $context;
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

    public function addHistory(string $input): void
    {
        $input = trim($input);
        if ($input !== "") {
            $this->inputHistory[] = $input;
        }
        $this->historyIndex = -1;
        $this->tempInput = "";
    }
    public function navigateHistory(string $direction): ?string
    {
        if (empty($this->inputHistory)) {
            return null;
        }
        $count = count($this->inputHistory);
        if ($direction === "up") {
            if ($this->historyIndex === -1) {
                $this->tempInput = $this->inputText;
                $this->historyIndex = $count - 1;
            } else {
                $this->historyIndex = max(0, $this->historyIndex - 1);
            }
            return $this->inputHistory[$this->historyIndex];
        }
        if ($direction === "down") {
            if ($this->historyIndex === -1) {
                return null;
            }
            $this->historyIndex++;
            if ($this->historyIndex >= $count) {
                $this->historyIndex = -1;
                return $this->tempInput;
            }
            return $this->inputHistory[$this->historyIndex];
        }
        return null;
    }

    public function getLoadedSystemSkills(): array
    {
        return $this->loadedSystemSkills;
    }
    public function setLoadedSystemSkills(array $skills): void
    {
        $this->loadedSystemSkills = $skills;
    }
    public function getSystemSkillContext(): string
    {
        return $this->systemSkillContext;
    }
    public function setSystemSkillContext(string $context): void
    {
        $this->systemSkillContext = $context;
    }
    public function getLoadedUserSkills(): array
    {
        return $this->loadedUserSkills;
    }
    public function setLoadedUserSkills(array $skills): void
    {
        $this->loadedUserSkills = $skills;
    }
    public function getUserSkillContext(): string
    {
        return $this->userSkillContext;
    }
    public function setUserSkillContext(string $context): void
    {
        $this->userSkillContext = $context;
    }

    // --- EDITOR METHODS ---
    public function isEditorOpen(): bool
    {
        return $this->editorOpen;
    }
    public function setEditorOpen(bool $open): void
    {
        $this->editorOpen = $open;
    }
    public function getEditorFilePath(): string
    {
        return $this->editorFilePath;
    }
    public function setEditorFilePath(string $path): void
    {
        $this->editorFilePath = $path;
    }
    public function getEditorLines(): array
    {
        return $this->editorLines;
    }
    public function setEditorLines(array $lines): void
    {
        $this->editorLines = empty($lines) ? [""] : $lines;
        $this->editorRow = 0;
        $this->editorCol = 0;
        $this->editorStatus = "Ready";
        $this->editorScrollY = 0;
    }
    public function getEditorRow(): int
    {
        return $this->editorRow;
    }
    public function setEditorRow(int $row): void
    {
        $this->editorRow = max(0, min(count($this->editorLines) - 1, $row));
    }
    public function getEditorCol(): int
    {
        return $this->editorCol;
    }
    public function setEditorCol(int $col): void
    {
        $this->editorCol = max(
            0,
            min(mb_strlen($this->editorLines[$this->editorRow]), $col),
        );
    }
    public function getEditorStatus(): string
    {
        return $this->editorStatus;
    }
    public function setEditorStatus(string $status): void
    {
        $this->editorStatus = $status;
    }
    public function getEditorScrollY(): int
    {
        return $this->editorScrollY;
    }
    public function setEditorScrollY(int $y): void
    {
        $this->editorScrollY = max(0, $y);
    }

    // --- SELECTION & CLIPBOARD METHODS ---
    public function getEditorSelection(): ?array
    {
        if (!$this->editorSelecting) {
            return null;
        }
        $startRow = $this->editorSelStartRow;
        $startCol = $this->editorSelStartCol;
        $endRow = $this->editorRow;
        $endCol = $this->editorCol;

        if (
            $startRow > $endRow ||
            ($startRow === $endRow && $startCol > $endCol)
        ) {
            return [
                "start" => [$endRow, $endCol],
                "end" => [$startRow, $startCol],
            ];
        }
        return ["start" => [$startRow, $startCol], "end" => [$endRow, $endCol]];
    }

    public function copyEditorSelection(): void
    {
        $sel = $this->getEditorSelection();
        if (!$sel) {
            return;
        }

        $sr = $sel["start"][0];
        $sc = $sel["start"][1];
        $er = $sel["end"][0];
        $ec = $sel["end"][1];

        if ($sr === $er) {
            $this->editorClipboard = mb_substr(
                $this->editorLines[$sr],
                $sc,
                $ec - $sc,
            );
        } else {
            $clip = [mb_substr($this->editorLines[$sr], $sc)];
            for ($i = $sr + 1; $i < $er; $i++) {
                $clip[] = $this->editorLines[$i];
            }
            $clip[] = mb_substr($this->editorLines[$er], 0, $ec);
            $this->editorClipboard = implode(
                "
",
                $clip,
            );
        }
        $this->editorStatus = "Copied selection";
        $this->clearEditorSelection();
    }

    public function deleteEditorSelection(): bool
    {
        $sel = $this->getEditorSelection();
        if (!$sel) {
            return false;
        }

        $sr = $sel["start"][0];
        $sc = $sel["start"][1];
        $er = $sel["end"][0];
        $ec = $sel["end"][1];

        $left = mb_substr($this->editorLines[$sr], 0, $sc);
        $right = mb_substr($this->editorLines[$er], $ec);

        array_splice($this->editorLines, $sr, $er - $sr + 1, [$left . $right]);

        $this->editorRow = $sr;
        $this->editorCol = $sc;
        $this->clearEditorSelection();
        $this->editorStatus = "Unsaved changes";
        return true;
    }

    public function cutEditorSelection(): void
    {
        if ($this->editorSelecting) {
            $this->copyEditorSelection();
            $this->deleteEditorSelection();
            $this->editorStatus = "Cut selection";
        }
    }

    public function pasteEditorClipboard(): void
    {
        if ($this->editorClipboard === "") {
            return;
        }
        if ($this->editorSelecting) {
            $this->deleteEditorSelection();
        }

        $chars = mb_str_split($this->editorClipboard);
        foreach ($chars as $char) {
            if (
                $char ===
                "
"
            ) {
                $this->enterEditorLine();
            } else {
                $this->insertEditorChar($char);
            }
        }
        $this->editorStatus = "Pasted selection";
    }

    public function startOrUpdateEditorSelection(): void
    {
        if (!$this->editorSelecting) {
            $this->editorSelecting = true;
            $this->editorSelStartRow = $this->editorRow;
            $this->editorSelStartCol = $this->editorCol;
        }
    }

    public function clearEditorSelection(): void
    {
        $this->editorSelecting = false;
    }

    public function insertEditorChar(string $char): void
    {
        if ($this->editorSelecting) {
            $this->deleteEditorSelection();
        }
        $line = $this->editorLines[$this->editorRow];
        $left = mb_substr($line, 0, $this->editorCol);
        $right = mb_substr($line, $this->editorCol);
        $this->editorLines[$this->editorRow] = $left . $char . $right;
        $this->editorCol += mb_strlen($char);
        $this->editorStatus = "Unsaved changes";
    }
    public function enterEditorLine(): void
    {
        if ($this->editorSelecting) {
            $this->deleteEditorSelection();
        }
        $line = $this->editorLines[$this->editorRow];
        $left = mb_substr($line, 0, $this->editorCol);
        $right = mb_substr($line, $this->editorCol);
        $this->editorLines[$this->editorRow] = $left;
        array_splice($this->editorLines, $this->editorRow + 1, 0, [$right]);
        $this->editorRow++;
        $this->editorCol = 0;
        $this->editorStatus = "Unsaved changes";
    }
    public function backspaceEditorChar(): void
    {
        if ($this->editorSelecting) {
            $this->deleteEditorSelection();
            return;
        }
        if ($this->editorSelecting) {
            $this->deleteEditorSelection();
            return;
        }
        if ($this->editorCol > 0) {
            $line = $this->editorLines[$this->editorRow];
            $left = mb_substr($line, 0, $this->editorCol - 1);
            $right = mb_substr($line, $this->editorCol);
            $this->editorLines[$this->editorRow] = $left . $right;
            $this->editorCol--;
            $this->editorStatus = "Unsaved changes";
        } elseif ($this->editorRow > 0) {
            $prevLen = mb_strlen($this->editorLines[$this->editorRow - 1]);
            $this->editorLines[$this->editorRow - 1] .=
                $this->editorLines[$this->editorRow];
            array_splice($this->editorLines, $this->editorRow, 1);
            $this->editorRow--;
            $this->editorCol = $prevLen;
            $this->editorStatus = "Unsaved changes";
        }
    }
}
