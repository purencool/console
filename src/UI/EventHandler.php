<?php
namespace App\UI;

use App\State\AppState;
use App\Services\KeyBindingService;
use App\UI\Component\EditorEventHandler;
use App\UI\Component\CharKeyEventHandler;
use App\UI\Component\Keybindings\NavigationHandler;
use App\UI\Component\Keybindings\TerminalSubmissionHandler;
use App\UI\Component\Keybindings\ChatSubmissionHandler;
use App\Services\LogService;
use App\Services\MouseInteractionService;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\Event\MouseEvent;

class EventHandler
{
    // Persist singletons statically across infinite static event invocations
    private static ?EditorEventHandler $editorEventHandler = null;
    private static ?TerminalSubmissionHandler $terminalSubmissionHandler = null;
    private static ?ChatSubmissionHandler $chatSubmissionHandler = null;
    private static ?NavigationHandler $navigationHandler = null;
    private static ?CharKeyEventHandler $charKeyEventHandler = null;

    /**
     * Central Event Processing Router.
     * Evaluates incoming hardware signals, processes shortcuts, and cascades data down.
     */
    public static function handle(
        Event $event,
        array $services,
        int $maxScroll,
        object $display,
    ): bool {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var KeyBindingService $bindingService */
        $bindingService = $services["keybindings"];
        /** @var LogService $logger */
        $logger = $services["log"];
        /** @var MouseInteractionService $mouseService */
        $mouseService = $services["mouse"];

        // Lazy-load and reuse the editor event handler instance
        self::$editorEventHandler ??= new EditorEventHandler();
        if (self::$editorEventHandler->handle($event, $services)) {
            return true;
        }

        $maxScroll = method_exists($state, "getMaxScroll")
            ? $state->getMaxScroll()
            : $maxScroll;

        // Delegate hardware mouse interactions
        if ($event instanceof MouseEvent) {
            if (
                $mouseService->handleEvent(
                    $event,
                    $services,
                    $maxScroll,
                    $display,
                )
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
                    if ($state->getInputFocus() === "chat") {
                        $state->historyUp(
                            fn($text) => $state->setInputText($text),
                            fn() => $state->getInputText(),
                        );
                    }
                    return true;

                case KeyBindingService::ACTION_HISTORY_DOWN:
                    if ($state->getInputFocus() === "chat") {
                        $state->historyDown(
                            fn($text) => $state->setInputText($text),
                        );
                    }
                    return true;

                case KeyBindingService::ACTION_PASTE:
                    if (
                        $event instanceof \PhpTui\Term\Event\CodedKeyEvent ||
                        $event instanceof \PhpTui\Term\Event\CharKeyEvent
                    ) {
                        /** @var \App\Services\ClipboardService $clipboard */
                        $clipboard = $services["clipboard"] ?? null;
                        if ($clipboard && method_exists($clipboard, "get")) {
                            $pastedText = $clipboard->get();
                            if (trim($pastedText) !== "") {
                                $state->insertInputText($pastedText);
                            }
                        }
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
            self::$terminalSubmissionHandler ??= new TerminalSubmissionHandler();
            self::$chatSubmissionHandler ??= new ChatSubmissionHandler();

            if (self::$terminalSubmissionHandler->handle($services)) {
                return true;
            }
            if (self::$chatSubmissionHandler->handle($services)) {
                return true;
            }
        }

        // Navigation Keys Fallback (PageUp/PageDown, Arrows navigation, Backspace)
        if ($event instanceof CodedKeyEvent) {
            self::$navigationHandler ??= new NavigationHandler();
            if (
                self::$navigationHandler->handle($event, $services, $maxScroll)
            ) {
                return true;
            }
        }

        // Standard Character Typing Buffer Pipeline Fallback
        if ($event instanceof CharKeyEvent) {
            self::$charKeyEventHandler ??= new CharKeyEventHandler();
            if (self::$charKeyEventHandler->handle($event, $services)) {
                return true;
            }
        }

        return false;
    }
}
