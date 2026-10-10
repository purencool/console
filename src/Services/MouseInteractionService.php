<?php
namespace App\Services;

use App\State\AppState;
use PhpTui\Term\Event\MouseEvent;

class MouseInteractionService
{
    /**
     * Translates hardware mouse coordinates and wheel signals into state adjustments.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handleEvent(
        MouseEvent $event,
        array $services,
        int $maxScroll,
        ?object $display = null,
    ): bool {
        /** @var AppState $state */
        $state = $services["state"] ?? ($services["state"] ?? null);

        if (!$state) {
            return false;
        }

        // 1. Recover Absolute Fresh Metrics from Central Singleton Layout Service
        if (
            isset($services["layout"]) &&
            method_exists($services["layout"], "getSliceChatHeight")
        ) {
            $maxScroll = $services["layout"]->getSliceChatHeight();
        }

        // Always fall back to AppState's tracking if available
        if (method_exists($state, "getMaxScroll")) {
            $maxScroll = $state->getMaxScroll();
        }

        // Extract action signatures safely across various PhpTui library releases
        $kindName = "";
        if (isset($event->kind->name)) {
            $kindName = $event->kind->name;
        } elseif (
            property_exists($event, "action") &&
            isset($event->action->name)
        ) {
            $kindName = $event->action->name;
        }

        $logPath = dirname(__DIR__, 2) . "/.config/error.log";

        // 🔬 EMERGENCY SIGNAL TRACE: Log raw un-filtered event signature instantly to disk
        @file_put_contents(
            $logPath,
            sprintf(
                "[Mouse Hardware Gate] Raw Event Intercepted! Kind Name: '%s' | App State Focus: '%s' | Active MaxScroll: %d\n",
                $kindName,
                $state->getInputFocus(),
                $maxScroll,
            ),
            FILE_APPEND,
        );

        $isScrollUp =
            $kindName === "ScrollUp" ||
            str_contains(strtolower($kindName), "up");
        $isScrollDown =
            $kindName === "ScrollDown" ||
            str_contains(strtolower($kindName), "down");

        if (!$isScrollUp && !$isScrollDown) {
            return false;
        }

        // =========================================================================
        // CASE A: FULLSCREEN FILE EDITOR VIEWPORT SCROLLING
        // =========================================================================
        if ($state->isEditorOpen()) {
            $termHeight =
                $display && method_exists($display, "size")
                    ? $display->size()->height
                    : 30;
            $visibleLines = max(1, $termHeight - 4);
            $maxLines = count($state->getEditorLines());
            $currentY = $state->getEditorScrollY();

            if ($isScrollUp) {
                $state->setEditorScrollY(max(0, $currentY - 2));
            } else {
                if ($maxLines > $visibleLines) {
                    $maxAllowedScroll = $maxLines - $visibleLines;
                    $state->setEditorScrollY(
                        min($maxAllowedScroll, $currentY + 2),
                    );
                }
            }
            return true;
        }

        // =========================================================================
        // CASE B: STANDARD CHAT WINDOW LOG SCROLLING (DUAL AXIS SAFETY PACK)
        // =========================================================================
        $oldOffset = $state->getScrollOffset();

        // SCROLL REVERSED DIRECTION INTERCEPT (Move Viewport UP)
        if ($kindName === "ScrollDown" || $kindName === "ScrollUp") {
            // We want to force a change away from 0. Let's provide a step shift toggle.
            // If the user scrolls up, we decrease the offset. If down, we increase it.
            // But if it's locking up, let's treat ANY wheel action as an unlock gesture!
            $state->setAutoScroll(false);

            // Try standard decrement to slide history upward
            if ($kindName === "ScrollDown") {
                $newOffset = max(0, $oldOffset - 1);
            } else {
                $newOffset = min($maxScroll, $oldOffset + 1);
            }

            $state->setScrollOffset($newOffset);

            if ($newOffset >= $maxScroll) {
                $state->setAutoScroll(true);
            }

            @file_put_contents(
                $logPath,
                sprintf(
                    "[Mouse Core Action Success] Shifted Offset from %d to %d | MaxScroll was: %d | AutoScroll forced to: %s\n",
                    $oldOffset,
                    $state->getScrollOffset(),
                    $maxScroll,
                    $state->isAutoScroll() ? "TRUE" : "FALSE",
                ),
                FILE_APPEND,
            );

            return true;
        }

        return false;
    }
}
