<?php
namespace App\UI;

use App\State\AppState;
use App\Services\KeyBindingService;
use App\UI\Component\EditorEventHandler;
use App\UI\Component\CharKeyEventHandler;
use App\UI\Component\Keybindings\NavigationHandler;
use App\UI\Component\Keybindings\TerminalSubmissionHandler;
use App\UI\Component\Keybindings\ChatSubmissionHandler;
use App\UI\Component\MouseEventHandler;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\Event\MouseEvent;

class EventHandler
{
    /**
     * Central Event Processing Router.
     * Evaluates incoming hardware signals, processes shortcuts, and cascades data down.
     */
    public static function handle(
        Event $event,
        array $services,
        int $maxScroll,
    ): bool {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var KeyBindingService $bindingService */
        $bindingService = $services["keybindings"];
        /** @var \App\Services\LogService $logger */
        $logger = $services["log"];

        // Delegate full-screen text editor input intercepts
        if (new EditorEventHandler()->handle($event, $services)) {
            return true;
        }

        $maxScroll = method_exists($state, "getMaxScroll")
            ? $state->getMaxScroll()
            : $maxScroll;

        // Delegate hardware mouse interactions
        if ($event instanceof MouseEvent) {
            if (
                new MouseEventHandler()->handle($event, $services, $maxScroll)
            ) {
                return true;
            }
        }

        // Centralised Action Resolution Engine via Shared Service Singleton
        $action = $bindingService->matchEvent($event);

        if ($action !== null) {
            switch ($action) {
                case KeyBindingService::ACTION_EXIT:
                case KeyBindingService::ACTION_FORCE_EXIT:
                    $state->stop();
                    return true;

                case KeyBindingService::ACTION_TOGGLE_TERMINAL:
                    $state->toggleTerminalDrawer();
                    return true;

                case KeyBindingService::ACTION_TOGGLE_SIDEBAR:
                    $state->toggleSidebar();
                    return true;

                case KeyBindingService::ACTION_TOGGLE_FOCUS:
                    $state->toggleInputFocus();
                    return true;

                case KeyBindingService::ACTION_HISTORY_UP:
                    if (method_exists($state, "historyUp")) {
                        $state->historyUp();
                        $logger->log(
                            sprintf(
                                "[Trace History] Prompt recalled UP: '%s'",
                                $state->getInputText(),
                            ),
                        );
                    }
                    return true;

                case KeyBindingService::ACTION_HISTORY_DOWN:
                    if (method_exists($state, "historyDown")) {
                        $state->historyDown();
                        $logger->log(
                            sprintf(
                                "[Trace History] Prompt recalled DOWN: '%s'",
                                $state->getInputText(),
                            ),
                        );
                    }
                    return true;

                case KeyBindingService::ACTION_PASTE:
                    $clipboard = $services["clipboard"] ?? null;
                    if ($clipboard && method_exists($clipboard, "get")) {
                        $state->insertInputText($clipboard::get());
                    }
                    return true;
            }
        }

        // Normalize Enum properties to safely match the Enter key action cross-platform
        $codeName =
            $event instanceof CodedKeyEvent &&
            is_object($event->code) &&
            property_exists($event->code, "name")
                ? $event->code->name
                : "";

        // Form Submission & Input Prompt Commands Routing Gateway
        if ($codeName === "Enter") {
            if (new TerminalSubmissionHandler()->handle($services)) {
                return true;
            }
            if (new ChatSubmissionHandler()->handle($services)) {
                return true;
            }
        }

        //Navigation Keys Fallback (PageUp/PageDown, Arrows navigation, Backspace)
        if ($event instanceof CodedKeyEvent) {
            if (
                new NavigationHandler()->handle($event, $services, $maxScroll)
            ) {
                return true;
            }
        }

        // Standard Character Typing Buffer Pipeline Fallback
        if ($event instanceof CharKeyEvent) {
            if (new CharKeyEventHandler()->handle($event, $services)) {
                return true;
            }
        }

        return false;
    }
}
