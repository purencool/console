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
     * Handles text auto-wrapping, dynamic scrolling offsets, processing spinners, and prefix syntax styling.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function render(array $services): ParagraphWidget
    {
        /** @var AppState $state */
        $state = $services["state"];

        /** @var TerminalLayoutService $layoutService */
        $layoutService = $services["layout"];

        $sliceChatHeight = $layoutService->getSliceChatHeight();
        $innerWidth = $layoutService->getInnerWidth();
        $spanClass = $layoutService->getSpanClass();
        $prefixes = $layoutService->getPrefixes();

        $metrics = $layoutService->getLayoutMetrics(hPad: 1, vPad: 1);
        $vPad = $metrics["vPad"] ?? 1;
        $hPadStr = $metrics["hPadStr"] ?? " ";

        $wrappedChatLog = [];
        $chatLogArray = $state->getChatLog();
        foreach ($chatLogArray as $logLine) {
            foreach (ReflectionHelper::wrapText($logLine, $innerWidth) as $wl) {
                if (trim($wl) !== "") {
                    $wrappedChatLog[] = $wl;
                }
            }
        }

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

        // --- Inside src/UI/Component/Renders/ChatRenderer.php ---

        // Dynamic Viewport Window Scroll Calculations
        $totalLines = count($wrappedChatLog);
        $maxScroll = max(0, $totalLines - $sliceChatHeight);

        if (method_exists($state, "setMaxScroll")) {
            $state->setMaxScroll($maxScroll);
        }

        $logPath = dirname(__DIR__, 4) . "/.config/error.log";
        $initialOffset = $state->getScrollOffset();
        $autoScrollState = $state->isAutoScroll() ? "TRUE" : "FALSE";

        // If auto-scroll is locked on, it forces the view to the bottom lines
        if (method_exists($state, "isAutoScroll") && $state->isAutoScroll()) {
            $state->setScrollOffset($maxScroll);
        }

        // Clamp the scroll boundaries within safe ranges
        $currentScroll = min(max(0, $state->getScrollOffset()), $maxScroll);
        if (method_exists($state, "setScrollOffset")) {
            $state->setScrollOffset($currentScroll);
        }

        // 🔍 LOG OUTPUT TRACKING MAP
        @file_put_contents(
            $logPath,
            sprintf(
                "[Renderer Loop] Total Lines: %d | Max Scroll Ceiling: %d | Incoming Offset: %d | Final Offset: %d | AutoScroll: %s\n",
                $totalLines,
                $maxScroll,
                $initialOffset,
                $currentScroll,
                $autoScrollState,
            ),
            FILE_APPEND,
        );

        $visibleLog = array_slice(
            $wrappedChatLog,
            $currentScroll,
            $sliceChatHeight,
        );

        // Construct Styled Lines Matrix
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
