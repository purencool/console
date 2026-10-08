<?php
namespace App\UI\Component\Renders;

use App\State\AppState;
use App\Support\ReflectionHelper;
use PhpTui\Tui\Text\Line;

class InputBoxRenderer
{
    /**
     * Handles wrapping, layout calculations, and cursor spans for the main input box.
     * Returns an array containing the processed lines and the calculated input block height.
     */
    public function render(
        AppState $state,
        int $innerWidth,
        int $vPad,
        string $hPadStr,
        string $spanClass,
        mixed $bgStyle,
    ): array {
        $text = $state->getInputText();
        $pos = $state->getInputCursor();
        $showCursor = (int) (microtime(true) * 2) % 2 === 0;
        $cursorChar =
            $pos >= mb_strlen($text) ? " " : mb_substr($text, $pos, 1);

        $left = mb_substr($text, 0, $pos);
        $right = mb_substr($text, $pos + 1);

        $chatDisplayInput = $left . "\x7F" . $right;
        $chatDisplayInput = str_replace("/n", "\n", $chatDisplayInput);

        $wrappedInputLines = [];
        foreach (explode("\n", $chatDisplayInput) as $rawLine) {
            if ($rawLine === "") {
                $wrappedInputLines[] = "";
            } else {
                foreach (
                    ReflectionHelper::wrapText($rawLine, $innerWidth)
                    as $wl
                ) {
                    $wrappedInputLines[] = $wl;
                }
            }
        }

        $inputTuiLines = [];
        for ($i = 0; $i < $vPad; $i++) {
            $inputTuiLines[] = Line::fromString("");
        }

        foreach ($wrappedInputLines as $wl) {
            if (str_contains($wl, "\x7F")) {
                $parts = explode("\x7F", $wl);
                $spans = [$spanClass::fromString($hPadStr)];
                if ($parts[0] !== "") {
                    $spans[] = $spanClass::fromString($parts[0]);
                }

                if ($state->getInputFocus() === "chat" && $showCursor) {
                    $spans[] = $spanClass
                        ::fromString($cursorChar)
                        ->style($bgStyle);
                } else {
                    $spans[] = $spanClass::fromString($cursorChar);
                }

                if (isset($parts[1]) && $parts[1] !== "") {
                    $spans[] = $spanClass::fromString($parts[1]);
                }
                $inputTuiLines[] = Line::fromSpans(...$spans);
            } else {
                $inputTuiLines[] = Line::fromString($hPadStr . $wl);
            }
        }

        for ($i = 0; $i < $vPad; $i++) {
            $inputTuiLines[] = Line::fromString("");
        }

        $inputBoxHeight = min(
            15,
            max(3 + $vPad * 2, count($inputTuiLines) + 2),
        );

        return [
            "lines" => $inputTuiLines,
            "height" => $inputBoxHeight,
        ];
    }
}
