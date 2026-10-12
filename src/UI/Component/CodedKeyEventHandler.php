<?php
namespace App\UI\Component;

use App\UI\Component\Keybindings\GlobalHotkeyHandler;
use App\UI\Component\Keybindings\NavigationHandler;
use App\UI\Component\Keybindings\TerminalSubmissionHandler;
use App\UI\Component\Keybindings\ChatSubmissionHandler;
use PhpTui\Term\Event\CodedKeyEvent;

class CodedKeyEventHandler
{
    // Persistent shared handler instances to prevent memory/signal accumulation leaks
    private ?GlobalHotkeyHandler $globalHotkeyHandler = null;
    private ?NavigationHandler $navigationHandler = null;
    private ?TerminalSubmissionHandler $terminalSubmissionHandler = null;
    private ?ChatSubmissionHandler $chatSubmissionHandler = null;

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
        /** @var \App\State\AppState $state */
        $state = $services["state"];

        // Dynamically read the true, live wrapped limits out of your app state machine!
        // This stops the boundaries from caching onto zero, completely unlocking your
        // viewport page shifts.
        $liveMaxScroll = method_exists($state, "getMaxScroll")
            ? $state->getMaxScroll()
            : $maxScroll;

        // Normalize Enum properties to safely read string signatures ('Up', 'Down', 'Enter')
        $codeName =
            is_object($event->code) && property_exists($event->code, "name")
                ? $event->code->name
                : (string) $event->code;

        // Lazy-load instances on demand and cache them for all subsequent key events
        $this->globalHotkeyHandler ??= new GlobalHotkeyHandler();
        $this->navigationHandler ??= new NavigationHandler();

        // Delegate core panel focus hotkeys and layout modifications
        if ($this->globalHotkeyHandler->handle($event, $services)) {
            return true;
        }

        // Pass the live, un-cached maximum boundaries constraint map downstream!
        if (
            $this->navigationHandler->handle($event, $services, $liveMaxScroll)
        ) {
            return true;
        }

        // Delegate form action submission routes
        if ($codeName === "Enter") {
            $this->terminalSubmissionHandler ??= new TerminalSubmissionHandler();
            $this->chatSubmissionHandler ??= new ChatSubmissionHandler();

            if ($this->terminalSubmissionHandler->handle($services)) {
                return true;
            }
            if ($this->chatSubmissionHandler->handle($services)) {
                return true;
            }
        }

        return false;
    }
}
