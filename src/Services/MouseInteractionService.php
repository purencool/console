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
        $state = $services["state"];

        // Use property_exists to prevent indefined Property warnings
        $kindName = "";
        if (isset($event->kind->name)) {
            $kindName = $event->kind->name;
        } elseif (
            property_exists($event, "action") &&
            isset($event->action->name)
        ) {
            $kindName = $event->action->name;
        }

        $isScrollUp = $kindName === "ScrollUp";
        $isScrollDown = $kindName === "ScrollDown";

        if (!$isScrollUp && !$isScrollDown) {
            return false;
        }

        //  Editor scrolling
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

        // Dashboard and chat window scrolling.
        if ($isScrollUp) {
            $state->setAutoScroll(false);
            $state->setScrollOffset(max(0, $state->getScrollOffset() - 2));
            return true;
        }

        // Scroll Down calculations matching current boundary clamping rules
        $newOffset = min($maxScroll, $state->getScrollOffset() + 2);
        $state->setScrollOffset($newOffset);

        if ($newOffset >= $maxScroll) {
            $state->setAutoScroll(true);
        }

        return true;
    }
}
