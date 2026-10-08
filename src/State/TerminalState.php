<?php
namespace App\State;

class TerminalState
{
    private string $activeTerminalInput = "";
    private int $terminalCursor = 0;
    private array $terminalBuffer = [];
    private int $terminalScrollOffset = 0;
    private bool $terminalDrawerOpen = false;

    public function getActiveTerminalInput(): string
    {
        return $this->activeTerminalInput;
    }
    public function setActiveTerminalInput(string $input): void
    {
        $this->activeTerminalInput = $input;
        $this->terminalCursor = mb_strlen($input);
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

    public function isTerminalDrawerOpen(): bool
    {
        return $this->terminalDrawerOpen;
    }
    public function toggleTerminalDrawer(): void
    {
        $this->terminalDrawerOpen = !$this->terminalDrawerOpen;
    }
}
