<?php
namespace App\UI\Component;

use App\State\AppState;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;

class EditorEventHandler
{
    /**
     * Hijacks and processes events specifically meant for full-screen editing mode.
     * Returns true if the event was captured, false otherwise.
     */
    public function handle(Event $event, AppState $state): bool
    {
        if (!$state->isEditorOpen()) {
            return false;
        }

        if ($event instanceof CodedKeyEvent) {
            if ($event->code === KeyCode::Esc) {
                $state->setEditorOpen(false);
                return true;
            }
if ($event->code === KeyCode::Up) {
    if ($state->getEditorRow() > 0) {
        $state->setEditorRow($state->getEditorRow() - 1);
    }
    return true;
}
if ($event->code === KeyCode::Down) {
    if ($state->getEditorRow() < count($state->getEditorLines()) - 1) {
        $state->setEditorRow($state->getEditorRow() + 1);
    }
    return true;
}
            if ($event->code === KeyCode::Left) {
                if ($state->getEditorCol() > 0) {
                    $state->setEditorCol($state->getEditorCol() - 1);
                } elseif ($state->getEditorRow() > 0) {
                    $state->setEditorRow($state->getEditorRow() - 1);
                    $state->setEditorCol(
                        mb_strlen(
                            $state->getEditorLines()[$state->getEditorRow()],
                        ),
                    );
                }
                return true;
            }
            if ($event->code === KeyCode::Right) {
                if (
                    $state->getEditorCol() <
                    mb_strlen($state->getEditorLines()[$state->getEditorRow()])
                ) {
                    $state->setEditorCol($state->getEditorCol() + 1);
                } elseif (
                    $state->getEditorRow() <
                    count($state->getEditorLines()) - 1
                ) {
                    $state->setEditorRow($state->getEditorRow() + 1);
                    $state->setEditorCol(0);
                }
                return true;
            }
            if ($event->code === KeyCode::Backspace) {
                $state->backspaceEditorChar();
                return true;
            }
            if ($event->code === KeyCode::Enter) {
                $state->enterEditorLine();
                return true;
            }
        }

        if ($event instanceof CharKeyEvent) {
            $isCtrl =
                ($event->modifiers & KeyModifiers::CONTROL) ===
                KeyModifiers::CONTROL;
            if ($isCtrl && strtolower($event->char) === "s") {
                $result = @file_put_contents(
                    $state->getEditorFilePath(),
                    implode("\n", $state->getEditorLines()),
                );
                if ($result !== false) {
                    $state->setEditorStatus("File saved successfully!");
                } else {
                    $state->setEditorStatus(
                        "ERROR: Failed to save file! Check permissions.",
                    );
                }
                return true;
            }

            if (
                ord($event->char) >= 32 &&
                $event->char !== "\x7f" &&
                !$isCtrl
            ) {
                $state->insertEditorChar($event->char);
                return true;
            }
        }

        // Catch all remaining input to avoid bleeding into chat buffers when the editor view is active
        return true;
    }
}
