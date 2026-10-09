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
use PhpTui\Term\Terminal;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\Event\MouseEvent;
use PhpTui\Term\KeyCode;

class EventHandler
{
    public static function handle(
        Event $event,
        Terminal $terminal,
        AppState $state,
        int $maxScroll,
        callable $logger,
        callable $artisanRunner,
        ?object $display = null,
    ): bool {
        // 1. Delegate full-screen editor mode captures first
        $editorHandler = new EditorEventHandler();
        if ($editorHandler->handle($event, $state)) {
            return true;
        }

        $maxScroll = method_exists($state, "getMaxScroll")
            ? $state->getMaxScroll()
            : $maxScroll;

        // 2. Delegate mouse wheel interactions
        if ($event instanceof MouseEvent) {
            $mouseHandler = new MouseEventHandler();
            if ($mouseHandler->handle($event, $state, $maxScroll)) {
                return true;
            }
        }

        // 3. Centralized Action Resolution Engine via KeyBindingService
        $bindingService = new KeyBindingService();
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
                    $logger("[Trace History] Match Event caught UP arrow.");
                    if (method_exists($state, "historyUp")) {
                        $state->historyUp();
                        $logger(
                            sprintf(
                                "[Trace History] Prompt is now: '%s'",
                                $state->getInputText(),
                            ),
                        );
                    } else {
                        $logger(
                            "[Trace History] ERROR: historyUp method does not exist on AppState!",
                        );
                    }
                    return true;

                case KeyBindingService::ACTION_HISTORY_DOWN:
                    $logger("[Trace History] Match Event caught DOWN arrow.");
                    if (method_exists($state, "historyDown")) {
                        $state->historyDown();
                        $logger(
                            sprintf(
                                "[Trace History] Prompt is now: '%s'",
                                $state->getInputText(),
                            ),
                        );
                    } else {
                        $logger(
                            "[Trace History] ERROR: historyDown method does not exist on AppState!",
                        );
                    }
                    return true;
                case KeyBindingService::ACTION_PASTE:
                    // todo
                    if (class_exists("App\Services\ClipboardService")) {
                        //   $pasted = \App\Services\ClipboardService::get();
                        //  $state->insertInputText($pasted);
                    }
                    return true;
            }
        }

        // 4. Fallback: Structural enter processing for chat/terminals
        if (
            $event instanceof CodedKeyEvent &&
            $event->code === KeyCode::Enter
        ) {
            if (new TerminalSubmissionHandler()->handle($state)) {
                return true;
            }
            if (new ChatSubmissionHandler()->handle($state, $logger)) {
                return true;
            }
        }

        // 5. Fallback: Core viewports navigation overrides (PageUp/PageDown, Left/Right arrows)
        if ($event instanceof CodedKeyEvent) {
            $navigationHandler = new NavigationHandler();
            if ($navigationHandler->handle($event, $state, $maxScroll)) {
                return true;
            }
        }

        // 6. Fallback: Alphanumeric standard character typing loops
        if ($event instanceof CharKeyEvent) {
            $charHandler = new CharKeyEventHandler();
            if ($charHandler->handle($event, $state)) {
                return true;
            }
        }

        return false;
    }
}
