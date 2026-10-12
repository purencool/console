<?php
namespace App\UI\Component\Renders;

use App\State\AppState;
use App\Services\TerminalLayoutService;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Style\Style;

// 🚀 FIXED: Extracted out of the loop. This compiles exactly once when PHP parses the file.
class TerminalViewportMock
{
    public function viewportArea()
    {
        return null;
    }
}

class TerminalRenderer
{
    // Persistent instance cache to avoid recreating the wrapper on every single frame tick
    private ?TerminalViewportMock $viewportMock = null;

    /**
     * Renders text layouts, buffer offsets, and terminal input cursors for the drawer window.
     *
     * @param array<string, object> $services Centralised system dependency registry
     * @return array<Line> Compiled array of line matrices ready for viewport projection
     */
    public function render(
        array $services,
        string $spanClass,
        Style $bgStyle,
        bool $showCursor,
    ): array {
        /** @var AppState $state */
        $state = $services["state"];

        /** @var TerminalLayoutService $layoutService */
        $layoutService = $services["layout"];

        // Dynamically read metrics and dimensions from your layout service
        $metrics = $layoutService->getLayoutMetrics(hPad: 1, vPad: 1);
        $vPad = $metrics["vPad"] ?? 1;
        $hPadStr = $metrics["hPadStr"] ?? " ";

        // Reuses the exact same mock instance instead of recreating an anonymous class
        $this->viewportMock ??= new TerminalViewportMock();
        $viewport = $layoutService->getDimensions($this->viewportMock);

        $terminalDrawerHeight = $state->isTerminalDrawerOpen()
            ? (int) ($viewport["height"] * 0.4)
            : 0;

        // Calculate Grid Viewport Dimensions safely
        $visibleTermHeight = max(2, $terminalDrawerHeight - 2);
        $sliceTermHeight = max(1, $visibleTermHeight - $vPad * 2 - 1);

        $bufferCount = count($state->getTerminalBuffer());
        $maxTermScroll = max(0, $bufferCount - $sliceTermHeight);
        $termScroll = min(
            max(0, $state->getTerminalScrollOffset()),
            $maxTermScroll,
        );

        $state->setTerminalScrollOffset($termScroll);

        // Slice exact window region out of the live background terminal shell buffer
        $visibleTermBuffer = array_slice(
            $state->getTerminalBuffer(),
            $termScroll,
            $sliceTermHeight,
        );

        // Build Render Lines Stack Array
        $termLines = [];
        for ($i = 0; $i < $vPad; $i++) {
            $termLines[] = Line::fromString("");
        }
        foreach ($visibleTermBuffer as $lineText) {
            $termLines[] = Line::fromString($hPadStr . $lineText);
        }

        // Dynamic Interactive Command Shell Prompt Construction ($ )
        $termText = $state->getActiveTerminalInput();
        $termPos = $state->getTerminalCursor();
        $textLen = mb_strlen($termText);

        $left = mb_substr($termText, 0, $termPos);
        $right = mb_substr($termText, $termPos + 1);
        $char = $termPos >= $textLen ? " " : mb_substr($termText, $termPos, 1);

        $spans = [$spanClass::fromString($hPadStr . "$ ")];

        if ($left !== "") {
            $spans[] = $spanClass::fromString($left);
        }

        // Apply cursor block background highlight if focus is locked and cursor blink interval matches
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
