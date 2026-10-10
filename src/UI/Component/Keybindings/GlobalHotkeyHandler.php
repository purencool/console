<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyModifiers;

class GlobalHotkeyHandler
{
    /**
     * Intercepts system-wide control combinations and handles workspace focus switching shortcuts.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(CodedKeyEvent $event, array $services): bool
    {
        /** @var AppState $state */
        $state = $services["state"];

        // Extract the code name string safely to guarantee compatibility across backend configurations
        $codeName =
            is_object($event->code) && property_exists($event->code, "name")
                ? $event->code->name
                : (string) $event->code;

        $isCtrl =
            ($event->modifiers & KeyModifiers::CONTROL) ===
            KeyModifiers::CONTROL;

        // Toggle focus alignment boundaries between panel windows when Ctrl+Tab is captured
        if ($isCtrl && $codeName === "Tab") {
            $state->toggleInputFocus();
            return true;
        }

        return false;
    }
}
