<?php
namespace App\UI\Component\Renders;

use App\State\AppState;
use App\Support\ReflectionHelper;
use App\Services\TerminalLayoutService;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Text;

class ChatRenderer
{
    /**
     * Handles text auto-wrapping, dynamic scrolling offsets, processing spinners,
     * and prefix syntax styling using a pure read-only layout view mapping pass.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function render(array $services): ParagraphWidget
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var TerminalLayoutService $layoutService */
        $layoutService = $services["layout"];

        // 💡 Direct child state reference targeting to bypass dynamic proxies cleanly
        $chatState = method_exists($state, "chat") ? $state->chat() : $state;

        $sliceChatHeight = $layoutService->getSliceChatHeight();
        if ($sliceChatHeight <= 0) {
            $sliceChatHeight = 21;
        }

        $innerWidth = $layoutService->getInnerWidth();
        if ($innerWidth <= 0) {
            $innerWidth = 40;
        }

        $spanClass = $layoutService->getSpanClass();
        $prefixes = $layoutService->getPrefixes();

        $metrics = $layoutService->getLayoutMetrics(hPad: 1, vPad: 1);
        $vPad = $metrics["vPad"] ?? 1;
        $hPadStr = $metrics["hPadStr"] ?? " ";

        // Ingest history lines straight from the master collection
        $chatLogArray = $chatState->getChatLog();

        // Perform text wrapping calculations
        $wrappedChatLog = [];
        foreach ($chatLogArray as $logLine) {
            foreach (ReflectionHelper::wrapText($logLine, $innerWidth) as $wl) {
                if (trim($wl) !== "") {
                    $wrappedChatLog[] = $wl;
                }
            }
        }

        // Render ongoing background microservice loaders
        if ($state->isProcessing()) {
            $spinnerFrames = ["⠋", "⠙", "⠹", "⠸", "⠼", "⠴", "⠦", "⠧", "⠇", "⠏"];
            $spinner =
                $spinnerFrames[
                    (int) (microtime(true) * 10) % count($spinnerFrames)
                ];
            $wrappedChatLog[] = "";
            $wrappedChatLog[] = sprintf(
                "%s [System] Processing request... (%ss)",
                $spinner,
                $state->getProcessingDuration(),
            );
        }

        // Compute layout limits
        $totalWrappedLines = count($wrappedChatLog);
        $maxScroll = max(0, $totalWrappedLines - $sliceChatHeight);

        // Sync constraints directly with keybinding modules
        if (method_exists($chatState, "setMaxScroll")) {
            $chatState->setMaxScroll($maxScroll);
        }

        // If auto-scroll is locked, snap down to the tail end of the array.
        // Otherwise, honor the precise user scroll position unmodified!
        $currentScroll = $chatState->getScrollOffset();
        if (
            method_exists($chatState, "isAutoScroll") &&
            $chatState->isAutoScroll()
        ) {
            $currentScroll = $maxScroll;
            if (method_exists($chatState, "setScrollOffset")) {
                $chatState->setScrollOffset($maxScroll);
            }
        } else {
            $currentScroll = min(max(0, $currentScroll), $maxScroll);
        }

        // Slice out the visible portion matching your visual view frame
        $visibleLog = array_slice(
            $wrappedChatLog,
            $currentScroll,
            $sliceChatHeight,
        );

        // Construct Styled Lines Matrix Layout
        $chatLines = [];
        for ($i = 0; $i < $vPad; $i++) {
            $chatLines[] = Line::fromString("");
        }

        foreach ($visibleLog as $msg) {
            if ($msg === "") {
                $chatLines[] = Line::fromString("");
                continue;
            }

            $lineStr = $hPadStr . $msg;
            $matched = false;

            foreach ($prefixes as $prefix => $style) {
                if (str_contains($msg, $prefix)) {
                    $pos = mb_strpos($lineStr, $prefix);
                    if ($pos === false) {
                        continue;
                    }

                    $left = mb_substr($lineStr, 0, $pos);
                    $right = mb_substr($lineStr, $pos + mb_strlen($prefix));

                    $spans = [];
                    if ($left !== "") {
                        $spans[] = $spanClass::fromString($left);
                    }
                    $spans[] = $spanClass::fromString($prefix)->style($style);
                    if ($right !== "") {
                        $spans[] = $spanClass::fromString($right);
                    }

                    $chatLines[] = Line::fromSpans(...$spans);
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $chatLines[] = Line::fromString($lineStr);
            }
        }

        for ($i = 0; $i < $vPad; $i++) {
            $chatLines[] = Line::fromString("");
        }

        return ParagraphWidget::fromText(Text::fromLines(...$chatLines));
    }
}
