<?php
namespace App\UI\Component;

use App\State\AppState;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\KeyModifiers;

class CharKeyEventHandler
{
    /**
     * Handles typing stream mutations safely without processing control hotkeys.
     */
    public function handle(CharKeyEvent $event, AppState $state): bool
    {
        $isCtrl =
            ($event->modifiers & KeyModifiers::CONTROL) ===
            KeyModifiers::CONTROL;

        // If it's a control sequence, let the matchEvent method handle it inside the orchestrator
        if ($isCtrl) {
            return false;
        }

        // Standard alphanumeric string insertions
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
