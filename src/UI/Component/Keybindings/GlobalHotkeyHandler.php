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
        if (
            ($event->modifiers & KeyModifiers::CONTROL) ===
            KeyModifiers::CONTROL
        ) {
            $codeName = "";
            if (
                is_object($event->code) &&
                property_exists($event->code, "name")
            ) {
                $codeName = $event->code->name;
            } elseif (is_scalar($event->code)) {
                $codeName = (string) $event->code;
            }

            $target = strtolower($codeName);

            if ($target === "t" || $event->code === KeyCode::Char) {
                $state->toggleTerminalDrawer();
                return true;
            }
            if ($target === "s") {
                $state->toggleSidebar();
                return true;
            }
            if ($event->code === KeyCode::Tab) {
                $state->toggleInputFocus();
                return true;
            }
        }
        return false;
    }
}
