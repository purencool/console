<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;
use PhpTui\Term\Event\CodedKeyEvent;

class NavigationHandler
{
    /**
     * Processes viewport scrolling modifiers alongside structural navigation cursor adjustments.
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

        // Grab the explicit, dedicated ChatState instance directly to guarantee value changes sync up!
        $chatState = method_exists($state, "chat") ? $state->chat() : $state;

        $codeName =
            is_object($event->code) && property_exists($event->code, "name")
                ? $event->code->name
                : (string) $event->code;

        switch ($codeName) {
            case "PageUp":
                // 💡 Change mutations to target the verified chat context explicitly
                $chatState->setAutoScroll(false);
                $chatState->setScrollOffset(
                    max(0, $chatState->getScrollOffset() - 15),
                );
                return true;

            case "PageDown":
                $chatState->setScrollOffset(
                    min($maxScroll, $chatState->getScrollOffset() + 15),
                );
                if ($chatState->getScrollOffset() >= $maxScroll) {
                    $chatState->setAutoScroll(true);
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
