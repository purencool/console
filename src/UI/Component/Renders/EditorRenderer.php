<?php

namespace App\UI\Component\Renders;

use App\State\AppState;
use App\Support\ReflectionHelper;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Text;

class EditorRenderer
{
    /**
     * Renders the interactive fullscreen text editor pane.
     */
    public function render(
        $display,
        AppState $state,
        int $termHeight,
        bool $showCursor,
        int $vPad,
        string $hPadStr,
    ): void {
        $constraintClass = ReflectionHelper::getConstraintClass();

        $spanClass = class_exists("\PhpTui\Tui\Text\Span")
            ? "\PhpTui\Tui\Text\Span"
            : "\PhpTui\Tui\Model\Widget\Span";
        $styleClass = class_exists("\PhpTui\Tui\Style\Style")
            ? "\PhpTui\Tui\Style\Style"
            : "\PhpTui\Tui\Model\Style";
        $colorClass = class_exists("\PhpTui\Tui\Color\AnsiColor")
            ? "\PhpTui\Tui\Color\AnsiColor"
            : "\PhpTui\Tui\Model\Color\AnsiColor";

        $bgStyle = $styleClass::default()->bg($colorClass::DarkGray);
        $lineNumStyle = $styleClass::default()->fg($colorClass::DarkGray);

        $lines = $state->getEditorLines();
        $row = $state->getEditorRow();
        $col = $state->getEditorCol();

        // Compute viewport layout bounds
        $visibleLines = max(1, $termHeight - 2 - $vPad * 2);
        $scrollY = $state->getEditorScrollY();
        if ($row < $scrollY) {
            $scrollY = $row;
        } elseif ($row >= $scrollY + $visibleLines) {
            $scrollY = $row - $visibleLines + 1;
        }
        $state->setEditorScrollY($scrollY);

        $renderLines = array_slice($lines, $scrollY, $visibleLines);
        $tuiLines = [];

        for ($i = 0; $i < $vPad; $i++) {
            $tuiLines[] = Line::fromString("");
        }

        // Calculate dynamic line number column width boundaries
        $totalLines = count($lines);
        $lineNumWidth = max(3, strlen((string) $totalLines));

        foreach ($renderLines as $idx => $l) {
            $actualLineNum = $scrollY + $idx + 1;
            $lineNumStr =
                str_pad(
                    (string) $actualLineNum,
                    $lineNumWidth,
                    " ",
                    STR_PAD_LEFT,
                ) . " │ ";

            if ($idx === $row - $scrollY) {
                $cCol = $col;
                $char = $cCol >= mb_strlen($l) ? " " : mb_substr($l, $cCol, 1);
                $left = mb_substr($l, 0, $cCol);
                $right = mb_substr($l, $cCol + 1);

                $spans = [
                    $spanClass::fromString($hPadStr),
                    $spanClass::fromString($lineNumStr)->style($lineNumStyle),
                ];

                if ($left !== "") {
                    $spans[] = $spanClass::fromString($left);
                }

                $spans[] = $showCursor
                    ? $spanClass::fromString($char)->style($bgStyle)
                    : $spanClass::fromString($char);

                if ($right !== "") {
                    $spans[] = $spanClass::fromString($right);
                }
                $tuiLines[] = Line::fromSpans(...$spans);
            } else {
                $spans = [
                    $spanClass::fromString($hPadStr),
                    $spanClass::fromString($lineNumStr)->style($lineNumStyle),
                    $spanClass::fromString($l),
                ];
                $tuiLines[] = Line::fromSpans(...$spans);
            }
        }

        for ($i = 0; $i < $vPad; $i++) {
            $tuiLines[] = Line::fromString("");
        }

        $statusText = $state->getEditorStatus() ?: "Ready";
        $title = sprintf(
            " Editor: %s [Row: %d, Col: %d] [Ctrl+S: Save | Esc: Close] - %s ",
            $state->getEditorFilePath(),
            $row + 1,
            $col + 1,
            $statusText,
        );

        $display->draw(
            GridWidget::default()
                ->constraints($constraintClass::percentage(100))
                ->widgets(
                    ReflectionHelper::createBlock($title)->widget(
                        ParagraphWidget::fromText(
                            Text::fromLines(...$tuiLines),
                        ),
                    ),
                ),
        );
    }
}
