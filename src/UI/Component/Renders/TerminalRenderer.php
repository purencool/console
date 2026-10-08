<?php
namespace App\UI\Component\Renders;

use App\State\AppState;
use PhpTui\Tui\Text\Line;

class TerminalRenderer
{
    /**
     * Handles layout calculations, terminal buffer slicing,
     * cursor spans, and heights for the terminal drawer.
     */
    public function render(
        AppState $state,
        int $terminalDrawerHeight,
        int $vPad,
        string $hPadStr,
        string $spanClass,
        mixed $bgStyle,
        bool $showCursor,
    ): array {
        $visibleTermHeight = max(2, $terminalDrawerHeight - 2);
        $sliceTermHeight = max(1, $visibleTermHeight - $vPad * 2 - 1);

        $maxTermScroll = max(
            0,
            count($state->getTerminalBuffer()) - $sliceTermHeight,
        );
        $termScroll = min(
            max(0, $state->getTerminalScrollOffset()),
            $maxTermScroll,
        );
        $state->setTerminalScrollOffset($termScroll);

        $visibleTermBuffer = array_slice(
            $state->getTerminalBuffer(),
            $termScroll,
            $sliceTermHeight,
        );

        $termLines = [];
        for ($i = 0; $i < $vPad; $i++) {
            $termLines[] = Line::fromString("");
        }
        foreach ($visibleTermBuffer as $l) {
            $termLines[] = Line::fromString($hPadStr . $l);
        }

        $termText = $state->getActiveTerminalInput();
        $termPos = $state->getTerminalCursor();
        $char =
            $termPos >= mb_strlen($termText)
                ? " "
                : mb_substr($termText, $termPos, 1);
        $left = mb_substr($termText, 0, $termPos);
        $right = mb_substr($termText, $termPos + 1);

        $spans = [$spanClass::fromString($hPadStr . "$ ")];
        if ($left !== "") {
            $spans[] = $spanClass::fromString($left);
        }

        if ($state->getInputFocus() === "terminal" && $showCursor) {
            $spans[] = $spanClass::fromString($char)->style($bgStyle);
        } else {
            $spans[] = $spanClass::fromString($char);
        }

        if ($right !== "") {
            $spans[] = $spanClass::fromString($right);
        }

        $termLines[] = Line::fromSpans(...$spans);
        for ($i = 0; $i < $vPad; $i++) {
            $termLines[] = Line::fromString("");
        }

        return $termLines;
    }
}
