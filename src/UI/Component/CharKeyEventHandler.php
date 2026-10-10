<?php
namespace App\UI\Component;

use App\State\AppState;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\KeyModifiers;

class CharKeyEventHandler
{
    /**
     * Routes alphanumeric keyboard inputs into the active focused text buffer.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(CharKeyEvent $event, array $services): bool
    {
        /** @var AppState $state */
        $state = $services["state"];

        // Defer control modifiers to the global action matching orchestrator layers
        $isCtrl =
            ($event->modifiers & KeyModifiers::CONTROL) ===
            KeyModifiers::CONTROL;
        if ($isCtrl) {
            return false;
        }

        // Validate and insert printable characters (excluding control characters and delete keys)
        if (ord($event->char) >= 32 && $event->char !== "\x7f") {
            if (
                $state->isTerminalDrawerOpen() &&
                $state->getInputFocus() === "terminal"
            ) {
                $state->insertActiveTerminalInput($event->char);
            } else {
                $state->insertInputText($event->char);
            }
            return true;
        }

        return false;
    }
}
