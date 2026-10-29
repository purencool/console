<?php
namespace App\UI\Component\Editor;

use App\State\AppState;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CodedKeyEvent;

class EditorTextPipelineHandler
{
    private string $activeFocusContext = "editor";

    /**
     * Handles text pipeline navigation using clean, decoupled action mappings.
     */
    public function handle(
        Event $event,
        AppState $state,
        object $editor,
        bool $selectActive,
        ?string $action = null,
    ): bool {
        $sidebarOpen = method_exists($editor, "isCtrlKeyPressed")
            ? $editor->isCtrlKeyPressed()
            : false;

        // =========================================================================
        // 1. INTERCEPT DATA WHEN SIDEBAR FOCUS IS LOCKED (MAC COMPATIBLE)
        // =========================================================================
        if ($sidebarOpen || $this->activeFocusContext === "sidebar") {
            $items = method_exists($editor, "getSidebarItems")
                ? $editor->getSidebarItems()
                : [];
            if (empty($items) && method_exists($editor, "refreshSidebarTree")) {
                $editor->refreshSidebarTree();
                $items = $editor->getSidebarItems();
            }

            $currentRow = method_exists($editor, "getSidebarFocusRow")
                ? $editor->getSidebarFocusRow()
                : 0;

            // A. Process centralized, clean Mac Action hooks first
            if ($action !== null) {
                switch ($action) {
                    case \App\Services\KeyBindingService::ACTION_HISTORY_UP:
                        if (
                            $currentRow > 0 &&
                            method_exists($editor, "setSidebarFocusRow")
                        ) {
                            $editor->setSidebarFocusRow($currentRow - 1);
                        }
                        return true;

                    case \App\Services\KeyBindingService::ACTION_HISTORY_DOWN:
                        if (
                            $currentRow < count($items) - 1 &&
                            method_exists($editor, "setSidebarFocusRow")
                        ) {
                            $editor->setSidebarFocusRow($currentRow + 1);
                        }
                        return true;

                    case \App\Services\KeyBindingService::ACTION_EDITOR_EXECUTE_SELECT:
                        return $this->triggerSelection(
                            $items,
                            $currentRow,
                            $state,
                            $editor,
                        );
                }
            }

            // B. Hardware Coded key fallbacks (e.g. raw Return/Enter buttons)
            if ($event instanceof CodedKeyEvent) {
                $codeName =
                    is_object($event->code) &&
                    property_exists($event->code, "name")
                        ? $event->code->name
                        : (string) $event->code;
                switch ($codeName) {
                    case "Up":
                        if (
                            $currentRow > 0 &&
                            method_exists($editor, "setSidebarFocusRow")
                        ) {
                            $editor->setSidebarFocusRow($currentRow - 1);
                        }
                        return true;
                    case "Down":
                        if (
                            $currentRow < count($items) - 1 &&
                            method_exists($editor, "setSidebarFocusRow")
                        ) {
                            $editor->setSidebarFocusRow($currentRow + 1);
                        }
                        return true;
                    case "Enter":
                    case "Space":
                        return $this->triggerSelection(
                            $items,
                            $currentRow,
                            $state,
                            $editor,
                        );
                    case "Esc":
                        $state->setEditorOpen(false);
                        return true;
                }
            }

            return true; // Keep view focus locked while navigation tree is drawn
        }

        // =========================================================================
        //  2. STANDARD TEXT EDITING CANVAS ROUTER (RUNS WHEN CTRL IS RELEASED)
        // =========================================================================
        if ($event instanceof CodedKeyEvent) {
            $codeName =
                is_object($event->code) && property_exists($event->code, "name")
                    ? $event->code->name
                    : (string) $event->code;

            switch ($codeName) {
                case "Esc":
                    $state->setEditorOpen(false);
                    return true;

                case "Backspace":
                    $state->backspaceEditorChar();
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
                    }
                    return true;

                case "Right":
                    $currentLineLen = mb_strlen(
                        $state->getEditorLines()[$state->getEditorRow()],
                    );
                    if ($state->getEditorCol() < $currentLineLen) {
                        $state->setEditorCol($state->getEditorCol() + 1);
                    }
                    return true;

                case "Enter":
                    $state->enterEditorLine();
                    return true;
            }
        }

        return true;
    }

    /**
     * Executes folder expansion or file context loading safely.
     */
    private function triggerSelection(
        array $items,
        int $currentRow,
        AppState $state,
        object $editor,
    ): bool {
        $targetItem = $items[$currentRow] ?? null;
        if (!$targetItem) {
            return true;
        }

        if ($targetItem["is_dir"]) {
            if (method_exists($editor, "setSidebarScanRoot")) {
                $editor->setSidebarScanRoot($targetItem["path"]);
                $editor->setSidebarFocusRow(0);
                $editor->refreshSidebarTree();
            }
            $state->setEditorStatus(
                "Navigated to folder: " . $targetItem["path"],
            );
        } else {
            $filePath = $targetItem["path"];
            if (file_exists($filePath)) {
                $state->setEditorFilePath($filePath);
                $state->setEditorLines(
                    explode("\n", file_get_contents($filePath)),
                );
                $state->setEditorRow(0);
                $state->setEditorCol(0);
                $state->setEditorStatus(
                    "Loaded document successfully: " . basename($filePath),
                );

                // Kill the visibility override modifier tracking values
                if (method_exists($editor, "setCtrlKeyPressed")) {
                    $editor->setCtrlKeyPressed(false);
                }

                // Force flip input focus state natively back down into code view box channel
                $this->activeFocusContext = "editor";
            } else {
                $state->setEditorStatus(
                    "❌ Target file path error missing on disk!",
                );
            }
        }
        return true;
    }
}
