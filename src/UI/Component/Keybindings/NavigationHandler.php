<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;

class NavigationHandler
{
    /**
     * Processes scrolling keys alongside historical up/down recall arrow keys.
     */
    public function handle(
        CodedKeyEvent $event,
        AppState $state,
        int $maxScroll,
    ): bool {
        if ($event->code === KeyCode::PageUp) {
            $state->setAutoScroll(false);
            $state->setScrollOffset(max(0, $state->getScrollOffset() - 5));
            return true;
        }
        if ($event->code === KeyCode::PageDown) {
            $state->setScrollOffset(
                min($maxScroll, $state->getScrollOffset() + 5),
            );
            if ($state->getScrollOffset() >= $maxScroll) {
                $state->setAutoScroll(true);
            }
            return true;
        }

        if ($event->code === KeyCode::Backspace) {
            if (
                $state->isTerminalDrawerOpen() &&
                $state->getInputFocus() === "terminal"
            ) {
                $state->backspaceActiveTerminalInput();
            } else {
                $state->backspaceInputText();
            }
            return true;
        }

        // --- ARROW KEY ACTIONS (HISTORY & CURSOR MANAGEMENT) ---
        if ($event->code === KeyCode::Up) {
            if (
                $state->getInputFocus() === "chat" &&
                method_exists($state, "historyUp")
            ) {
                $state->historyUp();
                return true;
            }
        }
        if ($event->code === KeyCode::Down) {
            if (
                $state->getInputFocus() === "chat" &&
                method_exists($state, "historyDown")
            ) {
                $state->historyDown();
                return true;
            }
        }

        if ($event->code === KeyCode::Left) {
            if ($state->getInputFocus() === "chat") {
                $state->setInputCursor($state->getInputCursor() - 1);
            } else {
                $state->setTerminalCursor($state->getTerminalCursor() - 1);
            }
            return true;
        }
        if ($event->code === KeyCode::Right) {
            if ($state->getInputFocus() === "chat") {
                $state->setInputCursor($state->getInputCursor() + 1);
            } else {
                $state->setTerminalCursor($state->getTerminalCursor() + 1);
            }
            return true;
        }

        return false;
    }
}
