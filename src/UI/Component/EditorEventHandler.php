<?php
namespace App\UI\Component;

use App\State\AppState;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyModifiers;

class EditorEventHandler
{
    /**
     * Intercepts, isolates, and processes inputs exclusively when full-screen file editor mode is active.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(Event $event, array $services): bool
    {
        /** @var AppState $state */
        $state = $services["state"];

        if (!$state->isEditorOpen()) {
            return false;
        }

        if ($event instanceof CodedKeyEvent) {
            $codeName =
                is_object($event->code) && property_exists($event->code, "name")
                    ? $event->code->name
                    : (string) $event->code;

            switch ($codeName) {
                case "Esc":
                    $state->setEditorOpen(false);
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

                case "Backspace":
                    $state->backspaceEditorChar();
                    return true;

                case "Enter":
                    $state->enterEditorLine();
                    return true;
            }
        }

        if ($event instanceof CharKeyEvent) {
            $isCtrl =
                ($event->modifiers & KeyModifiers::CONTROL) ===
                KeyModifiers::CONTROL;

            // Handle File Persistence Write (Ctrl + S)
            if ($isCtrl && strtolower($event->char) === "s") {
                $payload = implode("\n", $state->getEditorLines());
                $result = @file_put_contents(
                    $state->getEditorFilePath(),
                    $payload,
                );

                $state->setEditorStatus(
                    $result !== false
                        ? "File saved successfully!"
                        : "ERROR: Failed to save file! Check permissions.",
                );
                return true;
            }

            // Route standard typed string bytes straight into the text layout buffer arrays
            if (
                !$isCtrl &&
                ord($event->char) >= 32 &&
                $event->char !== "\x7f"
            ) {
                $state->insertEditorChar($event->char);
                return true;
            }
        }

        // Catch all remaining hardware inputs to completely prevent character bleeding down into terminal/chat views
        return true;
    }
}
