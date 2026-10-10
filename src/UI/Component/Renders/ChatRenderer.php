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
     * @param array<string, object> $prefixes Text highlight style formatting matrices
     */
    public function render(
        array $services,
        int $sliceChatHeight,
        string $spanClass,
        array $prefixes,
    ): ParagraphWidget {
        /** @var AppState $state */
        $state = $services["state"];

        /** @var TerminalLayoutService $layoutService */
        $layoutService = $services["layout"];

        // Dynamically read dimensions and layout design tokens from the layout service
        $viewport = method_exists($state, "getSystemDrivers")
            ? $layoutService->getLayoutMetrics(hPad: 1, vPad: 1)
            : [];
        $vPad = $viewport["vPad"] ?? 1;
        $hPadStr = $viewport["hPadStr"] ?? " ";

        // Dynamically resolve inner grid width to prevent undefined $innerWidth bugs
        $viewportArea = method_exists($layoutService, "getDimensions")
            ? $layoutService->getDimensions(
                new class {
                    public function viewportArea()
                    {
                        return null;
                    }
                },
            )
            : ["width" => 100];
        $geometry = $layoutService->calculateGridGeometry(
            $viewportArea["width"],
            $state->isSidebarVisible(),
            $viewport["hPad"] ?? 1,
        );
        $innerWidth = $geometry["innerWidth"];

        $wrappedChatLog = [];
        $chatLogArray = $state->getChatLog();
        $chatLogCount = count($chatLogArray);

        // Wrap Chat Messages dynamically to match viewport constraints
        foreach ($chatLogArray as $idx => $logLine) {
            foreach (ReflectionHelper::wrapText($logLine, $innerWidth) as $wl) {
                $wrappedChatLog[] = $wl;
            }
            if ($idx < $chatLogCount - 1) {
                $wrappedChatLog[] = "";
            }
        }

        // 3. Handle Live Dynamic Progress Loading Spinner
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

        // 4. Dynamic Viewport Window Scroll Calculations
        $totalLines = count($wrappedChatLog);
        $maxScroll = max(0, $totalLines - $sliceChatHeight);

        if (method_exists($state, "setMaxScroll")) {
            $state->setMaxScroll($maxScroll);
        }

        // Snap focal window directly to bottom entries if autoscroll tracking is enabled
        if (
            method_exists($state, "isAutoScroll") &&
            $state->isAutoScroll() &&
            !$state->isProcessing()
        ) {
            $state->setScrollOffset($maxScroll);
        }

        // Keep active offset boundaries clamped securely within valid limits
        $currentScroll = min(max(0, $state->getScrollOffset()), $maxScroll);
        if (method_exists($state, "setScrollOffset")) {
            $state->setScrollOffset($currentScroll);
        }

        // Slice viewport window view log block segment
        $visibleLog = array_slice(
            $wrappedChatLog,
            $currentScroll,
            $sliceChatHeight,
        );

        // 5. Construct Styled Lines Matrix
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

            // Highlight prefixes dynamically using exact string matches
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
