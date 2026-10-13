<?php
namespace App\UI\Component\Editor;

use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyModifiers;

class EditorAiPromptHandler
{
    public function handle(
        Event $event,
        object $editor,
        callable $onExecute,
    ): bool {
        if ($event instanceof CharKeyEvent) {
            if (($event->modifiers & KeyModifiers::CONTROL) === 0) {
                if (method_exists($editor, "insertAiPromptChar")) {
                    $editor->insertAiPromptChar($event->char);
                }
                return true;
            }
        }

        if ($event instanceof CodedKeyEvent) {
            $codeName =
                is_object($event->code) && property_exists($event->code, "name")
                    ? $event->code->name
                    : (string) $event->code;
            switch ($codeName) {
                case "Backspace":
                    if (method_exists($editor, "backspaceAiPromptChar")) {
                        $editor->backspaceAiPromptChar();
                    }
                    return true;
                case "Esc":
                    if (method_exists($editor, "setAiPromptOpen")) {
                        $editor->setAiPromptOpen(false);
                    }
                    return true;
                case "Enter":
                    $onExecute();
                    return true;
            }
        }
        return true; // Keep keystrokes focus-locked inside the AI bar
    }
}
