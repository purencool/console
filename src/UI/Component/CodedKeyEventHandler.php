<?php
namespace App\UI\Component;

use App\State\AppState;
use App\Services\LlmService;
use App\UI\Component\Keybindings\GlobalHotkeyHandler;
use App\UI\Component\Keybindings\NavigationHandler;
use App\UI\Component\Keybindings\TerminalSubmissionHandler;
use App\UI\Component\Keybindings\ChatSubmissionHandler;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;

class CodedKeyEventHandler
{
    /**
     * Delegates event executions down into single-responsibility sub-handlers.
     */
    public function handle(
        CodedKeyEvent $event,
        AppState $state,
        LlmService $llmService,
        int $maxScroll,
        callable $logger,
    ): bool {
        $globalHandler = new GlobalHotkeyHandler();
        if ($globalHandler->handle($event, $state)) {
            return true;
        }

        $navigationHandler = new NavigationHandler();
        if ($navigationHandler->handle($event, $state, $maxScroll)) {
            return true;
        }

        if ($event->code === KeyCode::Enter) {
            $terminalHandler = new TerminalSubmissionHandler();
            if ($terminalHandler->handle($state)) {
                return true;
            }

            $chatHandler = new ChatSubmissionHandler();
            if ($chatHandler->handle($state, $llmService, $logger)) {
                return true;
            }
        }

        return false;
    }
}
