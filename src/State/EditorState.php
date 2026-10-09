<?php
namespace App\State;

class EditorState
{
    private bool $editorOpen = false;
    private string $editorFilePath = "";
    private array $editorLines = [""];
    private int $editorRow = 0;
    private int $editorCol = 0;
    private string $editorStatus = "";
    private int $scrollX = 0;
    private int $scrollY = 0;

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
        $this->editorLines = $lines;
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
        $currentRowLength = mb_strlen(
            $this->editorLines[$this->editorRow] ?? "",
        );
        $this->editorCol = max(0, min($currentRowLength, $col));
    }

    public function getEditorStatus(): string
    {
        return $this->editorStatus;
    }
    public function setEditorStatus(string $status): void
    {
        $this->editorStatus = $status;
    }

    public function insertEditorChar(string $char): void
    {
        $line = $this->editorLines[$this->editorRow] ?? "";
        $left = mb_substr($line, 0, $this->editorCol);
        $right = mb_substr($line, $this->editorCol);
        $this->editorLines[$this->editorRow] = $left . $char . $right;
        $this->editorCol += mb_strlen($char);
    }

    public function backspaceEditorChar(): void
    {
        if ($this->editorCol > 0) {
            $line = $this->editorLines[$this->editorRow];
            $left = mb_substr($line, 0, $this->editorCol - 1);
            $right = mb_substr($line, $this->editorCol);
            $this->editorLines[$this->editorRow] = $left . $right;
            $this->editorCol--;
        } elseif ($this->editorRow > 0) {
            $prevLine = $this->editorLines[$this->editorRow - 1];
            $this->editorCol = mb_strlen($prevLine);
            $this->editorLines[$this->editorRow - 1] .=
                $this->editorLines[$this->editorRow];
            array_splice($this->editorLines, $this->editorRow, 1);
            $this->editorRow--;
        }
    }

    public function enterEditorLine(): void
    {
        $line = $this->editorLines[$this->editorRow];
        $left = mb_substr($line, 0, $this->editorCol);
        $right = mb_substr($line, $this->editorCol);

        $this->editorLines[$this->editorRow] = $left;
        array_splice($this->editorLines, $this->editorRow + 1, 0, [$right]);
        $this->editorRow++;
        $this->editorCol = 0;
    }

    // 🔥 FIXED: Added the required Scroll tracking methods for your Renderer
    public function getScrollY(): int
    {
        // Automatically sync viewport if row moves above top boundaries
        if ($this->editorRow < $this->scrollY) {
            $this->scrollY = $this->editorRow;
        }
        return $this->scrollY;
    }

    public function setScrollY(int $y): void
    {
        $this->scrollY = max(0, $y);
    }

    public function getScrollX(): int
    {
        if ($this->editorCol < $this->scrollX) {
            $this->scrollX = $this->editorCol;
        }
        return $this->scrollX;
    }

    public function setScrollX(int $x): void
    {
        $this->scrollX = max(0, $x);
    }
}
