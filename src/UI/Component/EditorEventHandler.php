<?php
namespace App\UI\Component;

use App\State\AppState;
use App\UI\Component\Editor\EditorGlobalHotkeyHandler;
use App\UI\Component\Editor\EditorAiPromptHandler;
use App\UI\Component\Editor\EditorTextPipelineHandler;
use App\UI\Component\Editor\EditorAiRefactorProcessor;
use PhpTui\Term\Event as TermEvent;
use PhpTui\Term\Event\CharKeyEvent;

class EditorEventHandler
{
    private EditorGlobalHotkeyHandler $hotkeyHandler;
    private EditorAiPromptHandler $aiHandler;
    private EditorTextPipelineHandler $textHandler;
    private EditorAiRefactorProcessor $aiProcessor;

    public function __construct()
    {
        $this->hotkeyHandler = new EditorGlobalHotkeyHandler();
        $this->aiHandler = new EditorAiPromptHandler();
        $this->textHandler = new EditorTextPipelineHandler();
        $this->aiProcessor = new EditorAiRefactorProcessor();
    }

    /**
     * Intercepts, isolates, and routes inputs exclusively when full-screen file editor mode is active.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(\PhpTui\Term\Event $event, array $services): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        if (!$state->isEditorOpen()) {
            return false;
        }

        $editor = method_exists($state, "editor") ? $state->editor() : $state;

        // 1. Process Global Control Combinos (Ctrl+A, Ctrl+B, Ctrl+S)
        if (
            $event instanceof CharKeyEvent &&
            $this->hotkeyHandler->handle($event, $state, $editor)
        ) {
            return true;
        }

        $aiPromptOpen = method_exists($editor, "isAiPromptOpen")
            ? $editor->isAiPromptOpen()
            : false;
        $selectActive = method_exists($editor, "isSelectModeActive")
            ? $editor->isSelectModeActive()
            : false;

        // 2. Delegate focus if AI Refactor entry widget is open
        if ($aiPromptOpen) {
            return $this->aiHandler->handle(
                $event,
                $editor,
                fn() => $this->aiProcessor->execute($services, $state, $editor),
            );
        }

        // 3. Delegate standard text mutations and arrow navigations
        return $this->textHandler->handle(
            $event,
            $state,
            $editor,
            $selectActive,
        );
    }
}
