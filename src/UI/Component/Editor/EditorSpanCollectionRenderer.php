<?php
namespace App\UI\Component\Editor;

class EditorSpanCollectionRenderer
{
    /**
     * Appends styled text token Spans onto an active line builder pipeline array matrix.
     */
    public static function buildSpans(
        array &$spans,
        array $segmentMeta,
        object $editor,
        int $currentVirtualRowIdx,
        int $cursorVirtualRowIndex,
        int $row,
        int $col,
        bool $showCursor,
        string $spanClass,
        object $bgStyle,
        object $selectionStyle,
    ): void {
        $segmentText = $segmentMeta["text"];
        $segLength = mb_strlen($segmentText);
        $realLineIdx = $segmentMeta["real_row"];

        if ($segmentText === "") {
            // Render trailing empty cursor placeholder block if landing on an empty line row
            if ($currentVirtualRowIdx === $cursorVirtualRowIndex) {
                $spans[] = $showCursor
                    ? $spanClass::fromString(" ")->style($bgStyle)
                    : $spanClass::fromString(" ");
            }
            return;
        }

        $selectActive = method_exists($editor, "isSelectModeActive")
            ? $editor->isSelectModeActive()
            : false;
        $startRow = method_exists($editor, "getSelectStartRow")
            ? $editor->getSelectStartRow()
            : 0;
        $startCol = method_exists($editor, "getSelectStartCol")
            ? $editor->getSelectStartCol()
            : 0;

        if ($selectActive) {
            for ($c = 0; $c < $segLength; $c++) {
                $realCharGlobalCol = $segmentMeta["start_col"] + $c;

                $insideSelection = EditorSelectionPainter::isCharacterSelected(
                    $realLineIdx,
                    $realCharGlobalCol,
                    $startRow,
                    $startCol,
                    $row,
                    $col,
                );

                $charToken = mb_substr($segmentText, $c, 1);

                // Render cursor context parameters inside selection frame paths
                if (
                    $currentVirtualRowIdx === $cursorVirtualRowIndex &&
                    $col - $segmentMeta["start_col"] === $c
                ) {
                    $spans[] = $showCursor
                        ? $spanClass::fromString($charToken)->style($bgStyle)
                        : $spanClass
                            ::fromString($charToken)
                            ->style($selectionStyle);
                } else {
                    $spans[] = $insideSelection
                        ? $spanClass
                            ::fromString($charToken)
                            ->style($selectionStyle)
                        : $spanClass::fromString($charToken);
                }
            }
        } else {
            // Standard cursor alignment positioning pass when selection mode is inactive
            if ($currentVirtualRowIdx === $cursorVirtualRowIndex) {
                $relativeCol = $col - $segmentMeta["start_col"];
                $char =
                    $relativeCol >= $segLength
                        ? " "
                        : mb_substr($segmentText, $relativeCol, 1);
                $left = mb_substr($segmentText, 0, $relativeCol);
                $right = mb_substr($segmentText, $relativeCol + 1);

                if ($left !== "") {
                    $spans[] = $spanClass::fromString($left);
                }
                $spans[] = $showCursor
                    ? $spanClass::fromString($char)->style($bgStyle)
                    : $spanClass::fromString($char);
                if ($right !== "") {
                    $spans[] = $spanClass::fromString($right);
                }
            } else {
                $spans[] = $spanClass::fromString($segmentText);
            }
        }
    }
}
