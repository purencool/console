<?php
namespace App\State;

class AppState
{
    private ChatState $chat;
    private TerminalState $terminal;
    private EditorState $editor;
    private SystemState $system;

    public function __construct()
    {
        $this->chat = new ChatState();
        $this->terminal = new TerminalState();
        $this->editor = new EditorState();
        $this->system = new SystemState();
    }

    // Direct Accessors for clean decoupled development
    public function chat(): ChatState
    {
        return $this->chat;
    }
    public function terminal(): TerminalState
    {
        return $this->terminal;
    }
    public function editor(): EditorState
    {
        return $this->editor;
    }
    public function system(): SystemState
    {
        return $this->system;
    }

    // --- SYSTEM CONTEXT PASS-THROUGHS ---
    public function isRunning(): bool
    {
        return $this->system->isRunning();
    }
    public function stop(): void
    {
        $this->system->stop();
    }
    public function getInputFocus(): string
    {
        return $this->system->getInputFocus();
    }

    public function setInputFocus(string $focus): void
    {
        $this->system->setInputFocus($focus);
    }
    public function toggleInputFocus(): void
    {
        $this->system->setInputFocus(
            $this->getInputFocus() === "chat" ? "terminal" : "chat",
        );
    }
    public function isSidebarVisible(): bool
    {
        return $this->system->isSidebarVisible();
    }
    public function toggleSidebar(): void
    {
        $this->system->toggleSidebar();
    }
    public function getScannedPath(): ?string
    {
        return $this->system->getScannedPath();
    }
    public function setScannedPath(?string $path): void
    {
        $this->system->setScannedPath($path);
    }
    public function isProcessing(): bool
    {
        return $this->system->isProcessing();
    }
    public function setProcessing(bool $processing): void
    {
        $this->system->setProcessing($processing);
    }
    public function getProcessingDuration(): float
    {
        return $this->system->getProcessingDuration();
    }
    public function getOptions(): array
    {
        return $this->system->getOptions();
    }
    public function toggleOption(string $key): void
    {
        $this->system->toggleOption($key);
    }
    public function addHistory(string $input): void
    {
        $this->system->addHistory($input);
    }

    // --- CHAT CONTEXT PASS-THROUGHS ---
    public function getInputText(): string
    {
        return $this->chat->getInputText();
    }
    public function setInputText(string $text): void
    {
        $this->chat->setInputText($text);
    }
    public function getInputCursor(): int
    {
        return $this->chat->getInputCursor();
    }
    public function setInputCursor(int $pos): void
    {
        $this->chat->setInputCursor($pos);
    }
    public function insertInputText(string $char): void
    {
        $this->chat->insertInputText($char);
    }
    public function backspaceInputText(): void
    {
        $this->chat->backspaceInputText();
    }
    public function getChatLog(): array
    {
        return $this->chat->getChatLog();
    }
    public function addChatMessage(string $msg): void
    {
        $this->chat->addChatMessage($msg);
    }
    public function getScrollOffset(): int
    {
        return $this->chat->getScrollOffset();
    }
    public function setScrollOffset(int $offset): void
    {
        $this->chat->setScrollOffset($offset);
    }
    public function getMaxScroll(): int
    {
        return $this->chat->getMaxScroll();
    }
    public function setMaxScroll(int $maxScroll): void
    {
        $this->chat->setMaxScroll($maxScroll);
    }
    public function isAutoScroll(): bool
    {
        return $this->chat->isAutoScroll();
    }
    public function setAutoScroll(bool $auto): void
    {
        $this->chat->setAutoScroll($auto);
    }

    // --- TERMINAL CONTEXT PASS-THROUGHS ---
    public function getActiveTerminalInput(): string
    {
        return $this->terminal->getActiveTerminalInput();
    }
    public function setActiveTerminalInput(string $input): void
    {
        $this->terminal->setActiveTerminalInput($input);
    }
    public function getTerminalCursor(): int
    {
        return $this->terminal->getTerminalCursor();
    }
    public function setTerminalCursor(int $pos): void
    {
        $this->terminal->setTerminalCursor($pos);
    }
    public function insertActiveTerminalInput(string $char): void
    {
        $this->terminal->insertActiveTerminalInput($char);
    }
    public function backspaceActiveTerminalInput(): void
    {
        $this->terminal->backspaceActiveTerminalInput();
    }
    public function getTerminalBuffer(): array
    {
        return $this->terminal->getTerminalBuffer();
    }
    public function addTerminalOutput(string $line): void
    {
        $this->terminal->addTerminalOutput($line);
    }
    public function getTerminalScrollOffset(): int
    {
        return $this->terminal->getTerminalScrollOffset();
    }
    public function setTerminalScrollOffset(int $offset): void
    {
        $this->terminal->setTerminalScrollOffset($offset);
    }
    public function isTerminalDrawerOpen(): bool
    {
        return $this->terminal->isTerminalDrawerOpen();
    }
    public function toggleTerminalDrawer(): void
    {
        $this->terminal->toggleTerminalDrawer();
    }

    // --- EDITOR CONTEXT PASS-THROUGHS ---
    public function isEditorOpen(): bool
    {
        return $this->editor->isEditorOpen();
    }
    public function setEditorOpen(bool $open): void
    {
        $this->editor->setEditorOpen($open);
    }
    public function getEditorFilePath(): string
    {
        return $this->editor->getEditorFilePath();
    }
    public function setEditorFilePath(string $path): void
    {
        $this->editor->setEditorFilePath($path);
    }
    public function getEditorLines(): array
    {
        return $this->editor->getEditorLines();
    }
    public function setEditorLines(array $lines): void
    {
        $this->editor->setEditorLines($lines);
    }
    public function getEditorRow(): int
    {
        return $this->editor->getEditorRow();
    }
    public function setEditorRow(int $row): void
    {
        $this->editor->setEditorRow($row);
    }
    public function getEditorCol(): int
    {
        return $this->editor->getEditorCol();
    }
    public function setEditorCol(int $col): void
    {
        $this->editor->setEditorCol($col);
    }
    public function getEditorStatus(): string
    {
        return $this->editor->getEditorStatus();
    }
    public function setEditorStatus(string $status): void
    {
        $this->editor->setEditorStatus($status);
    }
    public function insertEditorChar(string $char): void
    {
        $this->editor->insertEditorChar($char);
    }
    public function backspaceEditorChar(): void
    {
        $this->editor->backspaceEditorChar();
    }
    public function enterEditorLine(): void
    {
        $this->editor->enterEditorLine();
    }
}
