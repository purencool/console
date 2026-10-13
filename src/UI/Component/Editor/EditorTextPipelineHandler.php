<?php
namespace App\UI\Component\Editor;

use App\State\AppState;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;

class EditorTextPipelineHandler
{
    public function handle(
        Event $event,
        AppState $state,
        object $editor,
        bool $selectActive,
    ): bool {
        if ($event instanceof CodedKeyEvent) {
            $codeName =
                is_object($event->code) && property_exists($event->code, "name")
                    ? $event->code->name
                    : (string) $event->code;
            switch ($codeName) {
                case "Esc":
                    $state->setEditorOpen(false);
                    if (
                        $selectActive &&
                        method_exists($editor, "setSelectModeActive")
                    ) {
                        $editor->setSelectModeActive(false);
                    }
                    return true;

                case "Backspace":
                    if (
                        $selectActive &&
                        method_exists($editor, "deleteSelectedBlock")
                    ) {
                        $editor->deleteSelectedBlock();
                        $state->setEditorStatus(
                            "Selected text block deleted successfully.",
                        );
                    } else {
                        $state->backspaceEditorChar();
                    }
                    return true;

                case "Up":
                    if ($state->getEditorRow() > 0) {
                        $state->setEditorRow($state->getEditorRow() - 1);
                    }
                    return true;

                case "Down":
                    if (
                        $state->getEditorRow() <
                        count($state->getEditorLines()) - 1
                    ) {
                        $state->setEditorRow($state->getEditorRow() + 1);
                    }
                    return true;

                case "Left":
                    if ($state->getEditorCol() > 0) {
                        $state->setEditorCol($state->getEditorCol() - 1);
                    } elseif ($state->getEditorRow() > 0) {
                        $state->setEditorRow($state->getEditorRow() - 1);
                        $state->setEditorCol(
                            mb_strlen(
                                $state->getEditorLines()[
                                    $state->getEditorRow()
                                ],
                            ),
                        );
                    }
                    return true;

                case "Right":
                    $currentLineLen = mb_strlen(
                        $state->getEditorLines()[$state->getEditorRow()],
                    );
                    if ($state->getEditorCol() < $currentLineLen) {
                        $state->setEditorCol($state->getEditorCol() + 1);
                    } elseif (
                        $state->getEditorRow() <
                        count($state->getEditorLines()) - 1
                    ) {
                        $state->setEditorRow($state->getEditorRow() + 1);
                        $state->setEditorCol(0);
                    }
                    return true;

                case "Enter":
                    if (
                        $selectActive &&
                        method_exists($editor, "setSelectModeActive")
                    ) {
                        $editor->setSelectModeActive(false);
                    }
                    $state->enterEditorLine();
                    return true;
            }
        }

        if ($event instanceof CharKeyEvent) {
            if (ord($event->char) >= 32 && $event->char !== "\x7f") {
                if (
                    $selectActive &&
                    method_exists($editor, "setSelectModeActive")
                ) {
                    $editor->setSelectModeActive(false);
                }
                $state->insertEditorChar($event->char);
                return true;
            }
        }

        return true;
    }
}
