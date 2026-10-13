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

    private bool $aiPromptOpen = false;
    private string $aiPromptText = "";
    private int $aiPromptCursor = 0;
    private bool $selectModeActive = false;
    private int $selectStartRow = 0;
    private int $selectStartCol = 0;

    private bool $fileSidebarOpen = true;
    private array $sidebarItems = [];
    private int $sidebarFocusRow = 0;
    private string $sidebarScanRoot = ".";

    private bool $ctrlKeyPressed = false;

    public function isCtrlKeyPressed(): bool
    {
        return $this->ctrlKeyPressed;
    }

    public function setCtrlKeyPressed(bool $pressed): void
    {
        $this->ctrlKeyPressed = $pressed;
        if ($pressed) {
            $this->refreshSidebarTree();
        }
    }
    /**
     * Toggles the file directory tree panel open or closed.
     */
    public function toggleFileSidebar(): void
    {
        $this->fileSidebarOpen = !($this->fileSidebarOpen ?? true);
        if ($this->fileSidebarOpen) {
            $this->refreshSidebarTree();
        }
    }

    public function isFileSidebarOpen(): bool
    {
        return $this->fileSidebarOpen;
    }
    public function setFileSidebarOpen(bool $open): void
    {
        $this->fileSidebarOpen = $open;
    }

    public function getSidebarItems(): array
    {
        return $this->sidebarItems;
    }
    public function setSidebarItems(array $items): void
    {
        $this->sidebarItems = $items;
    }

    public function getSidebarFocusRow(): int
    {
        return $this->sidebarFocusRow;
    }
    public function setSidebarFocusRow(int $row): void
    {
        $this->sidebarFocusRow = $row;
    }

    public function getSidebarScanRoot(): string
    {
        return $this->sidebarScanRoot;
    }
    public function setSidebarScanRoot(string $path): void
    {
        $this->sidebarScanRoot = $path;
    }

    /**
     * Scans the target workspace root directory to generate a flattened directory map.
     */
    public function refreshSidebarTree(): void
    {
        $root = $this->sidebarScanRoot;
        if (!is_dir($root)) {
            return;
        }

        $items = [];

        // 💡 BREADCRUMB PARENT NAVIGATOR: If we are deep inside sub-folders,
        // inject a backtrack directory link block at the top of the tree stack!
        if ($root !== "." && $root !== "./" && $root !== "/") {
            $items[] = [
                "name" => "../",
                "path" => dirname($root),
                "is_dir" => true,
                "is_backtrack" => true,
            ];
        }

        $files = scandir($root);
        $foldersPool = [];
        $filesPool = [];

        foreach ($files as $f) {
            if (
                $f === "." ||
                $f === ".." ||
                $f === "vendor" ||
                $f === ".git" ||
                $f === "node_modules"
            ) {
                continue;
            }

            $fullPath = rtrim($root, "/") . "/" . $f;
            if (is_dir($fullPath)) {
                $foldersPool[] = [
                    "name" => $f . "/",
                    "path" => $fullPath,
                    "is_dir" => true,
                    "is_backtrack" => false,
                ];
            } else {
                $filesPool[] = [
                    "name" => $f,
                    "path" => $fullPath,
                    "is_dir" => false,
                    "is_backtrack" => false,
                ];
            }
        }

        $this->sidebarItems = array_merge($items, $foldersPool, $filesPool);
    }

    public function isSelectModeActive(): bool
    {
        return $this->selectModeActive;
    }
    public function setSelectModeActive(bool $active): void
    {
        $this->selectModeActive = $active;
    }

    public function getSelectStartRow(): int
    {
        return $this->selectStartRow;
    }
    public function setSelectStartRow(int $row): void
    {
        $this->selectStartRow = $row;
    }

    public function getSelectStartCol(): int
    {
        return $this->selectStartCol;
    }
    public function setSelectStartCol(int $col): void
    {
        $this->selectStartCol = $col;
    }

    public function isAiPromptOpen(): bool
    {
        return $this->aiPromptOpen;
    }
    public function setAiPromptOpen(bool $open): void
    {
        $this->aiPromptOpen = $open;
    }

    public function getAiPromptText(): string
    {
        return $this->aiPromptText;
    }
    public function setAiPromptText(string $text): void
    {
        $this->aiPromptText = $text;
    }

    public function insertAiPromptChar(string $char): void
    {
        $left = mb_substr($this->aiPromptText, 0, $this->aiPromptCursor);
        $right = mb_substr($this->aiPromptText, $this->aiPromptCursor);
        $this->aiPromptText = $left . $char . $right;
        $this->aiPromptCursor += mb_strlen($char);
    }

    public function backspaceAiPromptChar(): void
    {
        if ($this->aiPromptCursor > 0) {
            $left = mb_substr(
                $this->aiPromptText,
                0,
                $this->aiPromptCursor - 1,
            );
            $right = mb_substr($this->aiPromptText, $this->aiPromptCursor);
            $this->aiPromptText = $left . $right;
            $this->aiPromptCursor--;
        }
    }

    /**
     * Wipes out a batch block of lines cleanly between the starting anchor and current row cursor position.
     */
    public function deleteSelectedBlock(): void
    {
        if (!$this->selectModeActive) {
            return;
        }

        $currentRow = $this->getEditorRow();
        $startRow = $this->selectStartRow;

        $minRow = min($startRow, $currentRow);
        $maxRow = max($startRow, $currentRow);

        $lines = $this->getEditorLines();

        if ($minRow === $maxRow) {
            // 💡 Single Line Selection Deletion
            $currentLine = $lines[$minRow] ?? "";
            $startCol = $this->selectStartCol;
            $currentCol = $this->getEditorCol();

            $minCol = min($startCol, $currentCol);
            $maxCol = max($startCol, $currentCol);

            $left = mb_substr($currentLine, 0, $minCol);
            $right = mb_substr($currentLine, $maxCol);
            $lines[$minRow] = $left . $right;

            $this->setEditorCol($minCol);
        } else {
            // 💡 Multi-Line Selection Deletion
            $topLine = $lines[$minRow] ?? "";
            $bottomLine = $lines[$maxRow] ?? "";

            $startCol =
                $minRow === $startRow
                    ? $this->selectStartCol
                    : $this->getEditorCol();
            $endCol =
                $minRow === $startRow
                    ? $this->getEditorCol()
                    : $this->selectStartCol;

            // Retain text outside selection margins
            $left = mb_substr($topLine, 0, $startCol);
            $right = mb_substr($bottomLine, $endCol);

            $lines[$minRow] = $left . $right;

            // Remove all fully highlighted lines caught in between the selection frame
            array_splice($lines, $minRow + 1, $maxRow - $minRow);

            $this->setEditorRow($minRow);
            $this->setEditorCol($startCol);
        }

        $this->setEditorLines($lines);
        $this->selectModeActive = false;
    }

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
