<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;
use PhpTui\Term\Event\CodedKeyEvent;

class NavigationHandler
{
    /**
     * Processes viewport scrolling modifiers alongside historical up/down recall arrow keys.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(
        CodedKeyEvent $event,
        array $services,
        int $maxScroll,
    ): bool {
        /** @var AppState $state */
        $state = $services["state"];

        // Synchronise Dynamic Scroll Bounds
        $maxScroll = method_exists($state, "getMaxScroll")
            ? $state->getMaxScroll()
            : $maxScroll;

        // Enum-Safe String Identification Mapping Extraction
        $codeName =
            is_object($event->code) && property_exists($event->code, "name")
                ? $event->code->name
                : (string) $event->code;

        switch ($codeName) {
            case "PageUp":
                $state->setAutoScroll(false);
                $state->setScrollOffset(max(0, $state->getScrollOffset() - 5));
                return true;

            case "PageDown":
                $state->setScrollOffset(
                    min($maxScroll, $state->getScrollOffset() + 5),
                );
                if ($state->getScrollOffset() >= $maxScroll) {
                    $state->setAutoScroll(true);
                }
                return true;

            case "Backspace":
                if (
                    $state->isTerminalDrawerOpen() &&
                    $state->getInputFocus() === "terminal"
                ) {
                    $state->backspaceActiveTerminalInput();
                } else {
                    $state->backspaceInputText();
                }
                return true;

            case "Up":
                if (
                    $state->getInputFocus() === "chat" &&
                    method_exists($state, "historyUp")
                ) {
                    $state->historyUp();
                }
                return true;

            case "Down":
                if (
                    $state->getInputFocus() === "chat" &&
                    method_exists($state, "historyDown")
                ) {
                    $state->historyDown();
                }
                return true;

            case "Left":
                if ($state->getInputFocus() === "chat") {
                    $state->setInputCursor($state->getInputCursor() - 1);
                } else {
                    $state->setTerminalCursor($state->getTerminalCursor() - 1);
                }
                return true;

            case "Right":
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
