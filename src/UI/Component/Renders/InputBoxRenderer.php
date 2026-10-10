<?php
namespace App\UI\Component\Renders;

use App\State\AppState;
use App\Support\ReflectionHelper;
use App\Services\TerminalLayoutService;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Style\Style;

class InputBoxRenderer
{
    /**
     * Renders text wrappers, multi-line cursor locations, and bounds calculation for the chat input box.
     *
     * @param array<string, object> $services Centralised system dependency registry
     * @return array{lines: array<Line>, height: int} Compiled array of line matrices and calculated geometry height
     */
    public function render(
        array $services,
        int $innerWidth,
        string $spanClass,
        Style $bgStyle,
    ): array {
        /** @var AppState $state */
        $state = $services["state"];

        /** @var TerminalLayoutService $layoutService */
        $layoutService = $services["layout"];

        // 💡 1. Pull dynamic style tokens and layout paddings from your centralised layout service
        $metrics = $layoutService->getLayoutMetrics(hPad: 1, vPad: 1);
        $vPad = $metrics["vPad"] ?? 1;
        $hPadStr = $metrics["hPadStr"] ?? " ";

        $text = $state->getInputText();
        $pos = $state->getInputCursor();

        $showCursor = (int) (microtime(true) * 2) % 2 === 0;
        $isChatFocused = $state->getInputFocus() === "chat";
        $cursorChar =
            $pos >= mb_strlen($text) ? " " : mb_substr($text, $pos, 1);

        $left = mb_substr($text, 0, $pos);
        $right = mb_substr($text, $pos + 1);

        // Inject placeholder boundary character to explicitly pin tracking points across wrappers
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

        // Build TUI Render Lines Matrix
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

                // Highlight the active cursor position matching blinking interval constraints
                if ($isChatFocused && $showCursor) {
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

        // Compute Enclosing Dynamic Container Panel Heights safely using resolved vPad
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
