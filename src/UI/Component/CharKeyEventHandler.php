<?php
namespace App\UI\Component;

use App\State\AppState;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\KeyModifiers;

class CharKeyEventHandler
{
    /**
     * Handles standard alphanumeric keystrokes and text insertions
     * based on which input component currently holds active focus.
     */
    public function handle(CharKeyEvent $event, AppState $state): bool
    {
        $isCtrl =
            ($event->modifiers & KeyModifiers::CONTROL) ===
            KeyModifiers::CONTROL;

        // Block modification hotkeys or control sequences from entering string stream buffers
        if (!$isCtrl && ord($event->char) >= 32 && $event->char !== "\x7f") {
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
