<?php
namespace App\UI\Component;

use App\UI\Component\Keybindings\GlobalHotkeyHandler;
use App\UI\Component\Keybindings\NavigationHandler;
use App\UI\Component\Keybindings\TerminalSubmissionHandler;
use App\UI\Component\Keybindings\ChatSubmissionHandler;
use PhpTui\Term\Event\CodedKeyEvent;

class CodedKeyEventHandler
{
    /**
     * Delegates specific coded navigation keys down into specialized single-responsibility handlers.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(
        CodedKeyEvent $event,
        array $services,
        int $maxScroll,
    ): bool {
        // Normalize Enum properties to safely read string signatures ('Up', 'Down', 'Enter')
        $codeName =
            is_object($event->code) && property_exists($event->code, "name")
                ? $event->code->name
                : (string) $event->code;

        // Delegate core panel focus hotkeys and layout modifications
        if (new GlobalHotkeyHandler()->handle($event, $services)) {
            return true;
        }

        // Delegate viewport structural navigation overrides (Arrows, PageUp, Backspaces)
        if (new NavigationHandler()->handle($event, $services, $maxScroll)) {
            return true;
        }

        // Delegate form action submission routes
        if ($codeName === "Enter") {
            if (new TerminalSubmissionHandler()->handle($services)) {
                return true;
            }
            if (new ChatSubmissionHandler()->handle($services)) {
                return true;
            }
        }

        return false;
    }
}
