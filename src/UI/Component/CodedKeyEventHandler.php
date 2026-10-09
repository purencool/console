<?php
namespace App\UI\Component;

use App\State\AppState;
use App\Services\LlmService;
use App\Services\KeyBindingService;
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
        
        // 💡 1. Always evaluate global key actions first
        $keyBinder = new KeyBindingService();
        $action = $keyBinder->matchEvent($event);

        $focus = strtolower($state->getInputFocus());
        $isTerminalOpen = $state->isTerminalDrawerOpen();

        // ==========================================
        // 🎯 UP ARROW: RECALL PREVIOUS COMMAND
        // ==========================================
        if ($action === KeyBindingService::ACTION_HISTORY_UP) {
            $logger("[HISTORY] Up arrow captured. Focus: '{$focus}', Drawer Open: " . ($isTerminalOpen ? 'YES' : 'NO'));

            if ($focus === 'terminal' || ($isTerminalOpen && $focus !== 'chat')) {
                $state->cycleTermHistory(true);
                return true; // 💥 CRITICAL: Return true instantly to prevent sub-handlers from running!
            }
            
            if ($focus === 'chat') {
                $state->cycleChatHistory(true);
                return true; // 💥 CRITICAL: Return true instantly!
            }

            // Fallback to background screen scrolling if no inputs are active
            $state->setAutoScroll(false);
            $currentOffset = $state->getScrollOffset();
            if ($currentOffset > 0) {
                $state->setScrollOffset($currentOffset - 1);
            }
            return true;
        }

        // ==========================================
        // 🎯 DOWN ARROW: RECALL NEXT COMMAND
        // ==========================================
        if ($action === KeyBindingService::ACTION_HISTORY_DOWN) {
            $logger("[HISTORY] Down arrow captured. Focus: '{$focus}', Drawer Open: " . ($isTerminalOpen ? 'YES' : 'NO'));

            if ($focus === 'terminal' || ($isTerminalOpen && $focus !== 'chat')) {
                $state->cycleTermHistory(false);
                return true; 
            }
            
            if ($focus === 'chat') {
                $state->cycleChatHistory(false);
                return true; 
            }

            $currentOffset = $state->getScrollOffset();
            if ($currentOffset < $maxScroll) {
                $state->setScrollOffset($currentOffset + 1);
            }
            if ($state->getScrollOffset() >= $maxScroll) {
                $state->setAutoScroll(true);
            }
            return true;
        }
        
        // ==========================================
        // 💡 2. Only delegate to sub-handlers if no history buttons matched
        // ==========================================
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