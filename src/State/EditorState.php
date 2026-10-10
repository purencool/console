<?php
namespace App\State;

use ValueError;

/**
 *
 */
class EditorState
{
    /** @var bool */
    private bool $editorOpen = false;

    /** @var string */
    private string $editorFilePath = "";

    /** @var array */
    private array $editorLines = [""];

    /** @var int */
    private int $editorRow = 0;

    /** @var int */
    private int $editorCol = 0;

    /** @var string */
    private string $editorStatus = "";

    /** @var int */
    private int $scrollX = 0;

    /** @var int */
    private int $scrollY = 0;

    /**
     *
     */
    public function isEditorOpen(): bool
    {
        return $this->editorOpen;
    }

    /**
     *
     */
    public function setEditorOpen(bool $open): void
    {
        $this->editorOpen = $open;
    }

    /**
     *
     */
    public function getEditorFilePath(): string
    {
        return $this->editorFilePath;
    }

    /**
     *
     */
    public function setEditorFilePath(string $path): void
    {
        $this->editorFilePath = $path;
    }

    /**
     * @return array<mixed>
     */
    public function getEditorLines(): array
    {
        return $this->editorLines;
    }

    /**
     * @param array<mixed> $lines
     */
    public function setEditorLines(array $lines): void
    {
        $this->editorLines = $lines;
    }

    /**
     *
     */
    public function getEditorRow(): int
    {
        return $this->editorRow;
    }

    /**
     * @throws ValueError
     */
    public function setEditorRow(int $row): void
    {
        $this->editorRow = max(0, min(count($this->editorLines) - 1, $row));
    }

    /**
     *
     */
    public function getEditorCol(): int
    {
        return $this->editorCol;
    }

    /**
     * @throws ValueError
     */
    public function setEditorCol(int $col): void
    {
        $currentRowLength = mb_strlen(
            $this->editorLines[$this->editorRow] ?? "",
        );
        $this->editorCol = max(0, min($currentRowLength, $col));
    }

    /**
     *
     */
    public function getEditorStatus(): string
    {
        return $this->editorStatus;
    }

    /**
     *
     */
    public function setEditorStatus(string $status): void
    {
        $this->editorStatus = $status;
    }

    /**
     *
     */
    public function insertEditorChar(string $char): void
    {
        $line = $this->editorLines[$this->editorRow] ?? "";
        $left = mb_substr($line, 0, $this->editorCol);
        $right = mb_substr($line, $this->editorCol);
        $this->editorLines[$this->editorRow] = $left . $char . $right;
        $this->editorCol += mb_strlen($char);
    }

    /**
     *
     */
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

    /**
     *
     */
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

    /**
     *
     */
    public function getEditorScrollY(): int
    {
        // Automatically sync viewport if row moves above top boundaries
        if ($this->editorRow < $this->scrollY) {
            $this->scrollY = $this->editorRow;
        }
        return $this->scrollY;
    }

    /**
     * @throws ValueError
     */
    public function setEditorScrollY(int $y): void
    {
        $this->scrollY = max(0, $y);
    }

    /**
     *
     */
    public function getEditorScrollX(): int
    {
        if ($this->editorCol < $this->scrollX) {
            $this->scrollX = $this->editorCol;
        }
        return $this->scrollX;
    }

    /**
     * @throws ValueError
     */
    public function setEditorScrollX(int $x): void
    {
        $this->scrollX = max(0, $x);
    }

    /**
     * Scroll the editor view up or down by a specific number of lines.
     */
    public function scrollEditor(int $direction, int $visibleLines): void
    {
        $maxScrollY = max(0, count($this->editorLines) - $visibleLines);

        // $direction will be -1 for scroll up, +1 for scroll down
        $this->scrollY = max(0, min($maxScrollY, $this->scrollY + $direction));
    }
}
