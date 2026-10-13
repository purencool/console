<?php
namespace App\UI\Component\Editor;

class EditorSelectionPainter
{
    /**
     * Determines if a specific character column on a given line falls within the user's active block selection.
     *
     * @param int $realLineIdx The row index of the line in the document file buffer array
     * @param int $realCharGlobalCol The absolute character column index on that line
     * @param int $startRow The starting row anchor coordinate of the selection block
     * @param int $startCol The starting column anchor coordinate of the selection block
     * @param int $currentRow The active row cursor coordinate position
     * @param int $currentCol The active column cursor coordinate position
     */
    public static function isCharacterSelected(
        int $realLineIdx,
        int $realCharGlobalCol,
        int $startRow,
        int $startCol,
        int $currentRow,
        int $currentCol,
    ): bool {
        $minRow = min($startRow, $currentRow);
        $maxRow = max($startRow, $currentRow);

        // 1. Character is on a line fully enclosed between a multi-line selection
        if ($realLineIdx > $minRow && $realLineIdx < $maxRow) {
            return true;
        }

        // 2. Selection is entirely contained on a single line
        if ($realLineIdx === $minRow && $realLineIdx === $maxRow) {
            $minCol = min($startCol, $currentCol);
            $maxCol = max($startCol, $currentCol);
            return $realCharGlobalCol >= $minCol &&
                $realCharGlobalCol < $maxCol;
        }

        // 3. Character is on the top boundary row of a multi-line selection
        if ($realLineIdx === $minRow) {
            $boundaryCol = $startRow === $minRow ? $startCol : $currentCol;
            return $realCharGlobalCol >= $boundaryCol;
        }

        // 4. Character is on the bottom boundary row of a multi-line selection
        if ($realLineIdx === $maxRow) {
            $boundaryCol = $startRow === $maxRow ? $startCol : $currentCol;
            return $realCharGlobalCol < $boundaryCol;
        }

        return false;
    }
}
