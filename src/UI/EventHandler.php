<?php
namespace App\UI;

use App\State\AppState;
use App\Services\LlmService;
use App\UI\Component\EditorEventHandler;
use App\UI\Component\CodedKeyEventHandler;
use App\UI\Component\CharKeyEventHandler;
use App\UI\Component\MouseEventHandler;
use PhpTui\Term\Terminal;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\Event\MouseEvent;

class EventHandler
{
    public static function handle(
        Event $event,
        Terminal $terminal,
        AppState $state,
        LlmService $llmService,
        int $maxScroll,
        callable $logger,
        callable $artisanRunner,
        ?object $display = null,
    ): bool {
        // Delegate full-screen editor mode captures
        $editorHandler = new EditorEventHandler();
        if ($editorHandler->handle($event, $state)) {
            return true;
        }

        // Synchronize dynamic scrolling layout limits
        $maxScroll = method_exists($state, "getMaxScroll")
            ? $state->getMaxScroll()
            : $maxScroll;

        // Delegate mouse wheel interactions
        if ($event instanceof MouseEvent) {
            $mouseHandler = new MouseEventHandler();
            if ($mouseHandler->handle($event, $state, $maxScroll)) {
                return true;
            }
        }

        // Delegate coded keys, system hotkeys, and slash commands
        if ($event instanceof CodedKeyEvent) {
            $codedHandler = new CodedKeyEventHandler();
            if (
                $codedHandler->handle(
                    $event,
                    $state,
                    $llmService,
                    $maxScroll,
                    $logger,
                )
            ) {
                return true;
            }
        }

        // Handle standard text characters typing stream mutations
        if ($event instanceof CharKeyEvent) {
            $charHandler = new CharKeyEventHandler();
            if ($charHandler->handle($event, $state)) {
                return true;
            }
        }

        return false;
    }
}
