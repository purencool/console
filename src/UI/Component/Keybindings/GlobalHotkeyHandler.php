<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;

class GlobalHotkeyHandler
{
    public function handle(CodedKeyEvent $event, AppState $state): bool
    {
        // Intercept Control modifier actions for function keys
        if (
            ($event->modifiers & KeyModifiers::CONTROL) ===
            KeyModifiers::CONTROL
        ) {
            // Tab is a valid CodedKeyEvent code path
            if ($event->code === KeyCode::Tab) {
                $state->toggleInputFocus();
                return true;
            }
        }

        return false;
    }
}
