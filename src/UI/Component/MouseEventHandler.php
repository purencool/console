<?php
namespace App\UI\Component;

use App\State\AppState;
use PhpTui\Term\Event\MouseEvent;

class MouseEventHandler
{
    /**
     * Handles isolated terminal viewport mouse tracking configurations,
     * adjustments, and text coordinate wheel scrolling events.
     */
    public function handle(
        MouseEvent $event,
        AppState $state,
        int $maxScroll,
    ): bool {
        if (method_exists($event, "isScrollUp") && $event->isScrollUp()) {
            $state->setAutoScroll(false);
            $state->setScrollOffset(max(0, $state->getScrollOffset() - 2));
            return true;
        }

        if (method_exists($event, "isScrollDown") && $event->isScrollDown()) {
            $newOffset = min($maxScroll, $state->getScrollOffset() + 2);
            $state->setScrollOffset($newOffset);
            if ($newOffset >= $maxScroll) {
                $state->setAutoScroll(true);
            }
            return true;
        }

        return false;
    }
}
