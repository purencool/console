<?php
namespace App\UI\Component;

use App\State\AppState;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\KeyModifiers;

class CharKeyEventHandler
{
    /**
     * Handles typing stream mutations and alphanumeric Control hotkeys.
     */
    public function handle(CharKeyEvent $event, AppState $state): bool
    {
        $isCtrl = ($event->modifiers & KeyModifiers::CONTROL) === KeyModifiers::CONTROL;

        // --- ALPHANUMERIC CONTROL HOTKEYS ---
        if ($isCtrl) {
            $target = strtolower($event->char);
            
            if ($target === "t") {
                $state->toggleTerminalDrawer();
                return true;
            }
            if ($target === "s") {
                $state->toggleSidebar();
                return true;
            }
            return false;
        }

        // --- STANDARD TYPING FLOWS ---
        if (ord($event->char) >= 32 && $event->char !== "\x7f") {
            if ($state->isTerminalDrawerOpen() && $state->getInputFocus() === "terminal") {
                $state->insertActiveTerminalInput($event->char);
            } else {
                $state->insertInputText($event->char);
            }
            return true;
        }

        return false;
    }
}
