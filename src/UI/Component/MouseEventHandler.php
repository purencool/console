<?php
namespace App\UI\Component;

use App\State\AppState;
use PhpTui\Term\Event\MouseEvent;

class MouseEventHandler
{
    /**
     * Handles coordinate grid wheel scrolling events across terminal viewports.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(
        MouseEvent $event,
        array $services,
        int $maxScroll,
        ?object $display = null,
    ): bool {
        /** @var AppState $state */
        $state = $services["state"];

        // Extract action signatures safely across various PhpTui library releases
        $kindName = isset($event->kind->name)
            ? $event->kind->name
            : (isset($event->action->name)
                ? $event->action->name
                : "");
        $isScrollUp = $kindName === "ScrollUp";
        $isScrollDown = $kindName === "ScrollDown";

        if (!$isScrollUp && !$isScrollDown) {
            return false;
        }

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

        if ($isScrollUp) {
            $state->setAutoScroll(false);
            $state->setScrollOffset(max(0, $state->getScrollOffset() - 2));
            return true;
        }

        $newOffset = min($maxScroll, $state->getScrollOffset() + 2);
        $state->setScrollOffset($newOffset);

        if ($newOffset >= $maxScroll) {
            $state->setAutoScroll(true);
        }

        return true;
    }
}
