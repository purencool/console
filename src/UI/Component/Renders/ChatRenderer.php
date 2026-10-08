<?php
namespace App\UI\Component\Renders;

use App\State\AppState;
use App\Support\ReflectionHelper;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Text;

class ChatRenderer
{
    public function render(
        $display,
        AppState $state,
        int $innerWidth,
        int $sliceChatHeight,
        int $vPad,
        int $hPad,
        string $hPadStr,
        string $spanClass,
        array $prefixes,
    ): ParagraphWidget {
        $wrappedChatLog = [];
        $chatLogArray = $state->getChatLog();
        $chatLogCount = count($chatLogArray);

        // Wrap the chat messages
        foreach ($chatLogArray as $idx => $logLine) {
            foreach (ReflectionHelper::wrapText($logLine, $innerWidth) as $wl) {
                $wrappedChatLog[] = $wl;
            }
            if ($idx < $chatLogCount - 1) {
                $wrappedChatLog[] = "";
            }
        }

        // Handle the background processing spinner
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

        // --- DYNAMIC SCROLL MANAGEMENT ---
        $totalLines = count($wrappedChatLog);
        $maxScroll = max(0, $totalLines - $sliceChatHeight);

        // Save bounds into state so Application.php mouse listeners can read it
        if (method_exists($state, "setMaxScroll")) {
            $state->setMaxScroll($maxScroll);
        }

        // Automatically snap to bottom on new messages if autoscroll is true
        if (
            method_exists($state, "isAutoScroll") &&
            $state->isAutoScroll() &&
            !$state->isProcessing()
        ) {
            $state->setScrollOffset($maxScroll);
        }

        // Keep the current scrolling offset bounded within the safe calculated range
        $currentScroll = min(max(0, $state->getScrollOffset()), $maxScroll);
        if (method_exists($state, "setScrollOffset")) {
            $state->setScrollOffset($currentScroll);
        }

        $visibleLog = array_slice(
            $wrappedChatLog,
            $currentScroll,
            $sliceChatHeight,
        );

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
