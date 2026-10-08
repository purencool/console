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
    public function handle(
        CodedKeyEvent $event,
        AppState $state,
        LlmService $llmService,
        int $maxScroll,
        callable $logger,
    ): bool {
        if (new GlobalHotkeyHandler()->handle($event, $state)) {
            return true;
        }

        if (new NavigationHandler()->handle($event, $state, $maxScroll)) {
            return true;
        }

        if ($event->code === KeyCode::Enter) {
            if (new TerminalSubmissionHandler()->handle($state)) {
                return true;
            }
            if (
                new ChatSubmissionHandler()->handle(
                    $state,
                    $llmService,
                    $logger,
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
