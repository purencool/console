<?php
namespace App\UI\Component\Editor;

use App\State\AppState;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\KeyModifiers;

class EditorGlobalHotkeyHandler
{
    public function handle(
        CharKeyEvent $event,
        AppState $state,
        object $editor,
    ): bool {
        $isCtrl =
            ($event->modifiers & KeyModifiers::CONTROL) ===
            KeyModifiers::CONTROL;
        if (!$isCtrl) {
            return false;
        }

        $char = strtolower($event->char);

        // Toggle Inline AI Refactor Box (Ctrl + A)
        if ($char === "a") {
            $currentAiState = method_exists($editor, "isAiPromptOpen")
                ? $editor->isAiPromptOpen()
                : false;
            if (method_exists($editor, "setAiPromptOpen")) {
                $editor->setAiPromptOpen(!$currentAiState);
                if (method_exists($editor, "setAiPromptText")) {
                    $editor->setAiPromptText("");
                }
            }
            return true;
        }

        // Toggle Visual Block Selection Mode (Ctrl + B)
        if ($char === "b") {
            $currentSelectState = method_exists($editor, "isSelectModeActive")
                ? $editor->isSelectModeActive()
                : false;
            if (!$currentSelectState) {
                if (method_exists($editor, "setSelectModeActive")) {
                    $editor->setSelectModeActive(true);
                    if (method_exists($editor, "setSelectStartRow")) {
                        $editor->setSelectStartRow($state->getEditorRow());
                        $editor->setSelectStartCol($state->getEditorCol());
                    }
                }
                $state->setEditorStatus(
                    "Selection Mode [ACTIVE]. Expand highlight via Arrows.",
                );
            } else {
                if (method_exists($editor, "setSelectModeActive")) {
                    $editor->setSelectModeActive(false);
                }
                $state->setEditorStatus("Selection Mode [CANCELLED].");
            }
            return true;
        }

        // Handle File Persistence Write (Ctrl + S)
        if ($char === "s") {
            $payload = implode("\n", $state->getEditorLines());
            $result = @file_put_contents($state->getEditorFilePath(), $payload);
            $state->setEditorStatus(
                $result !== false
                    ? "File saved successfully!"
                    : "ERROR: Failed to save file!",
            );
            return true;
        }

        if ($char === "o") {
            if (method_exists($editor, "toggleFileSidebar")) {
                $editor->toggleFileSidebar();
            } else {
                $current = method_exists($editor, "isFileSidebarOpen")
                    ? $editor->isFileSidebarOpen()
                    : true;
                if (method_exists($editor, "setFileSidebarOpen")) {
                    $editor->setFileSidebarOpen(!$current);
                }
            }

            $status =
                method_exists($editor, "isFileSidebarOpen") &&
                $editor->isFileSidebarOpen()
                    ? "VISIBLE"
                    : "HIDDEN";
            $state->setEditorStatus("File sidebar tree is now: " . $status);
            return true;
        }

        return false;
    }
}
