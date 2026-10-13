<?php
namespace App\UI\Component\Editor;

use App\State\AppState;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;

class EditorTextPipelineHandler
{
    private string $activeFocusContext = "editor"; // Options: "sidebar", "editor"

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

            // 1. PANEL TOGGLE: Using the Tab Key to switch active navigation context manually
            if (
                $codeName === "Tab" &&
                method_exists($editor, "isFileSidebarOpen") &&
                $editor->isFileSidebarOpen()
            ) {
                $this->activeFocusContext =
                    $this->activeFocusContext === "editor"
                        ? "sidebar"
                        : "editor";
                $state->setEditorStatus(
                    "Focus shifted to panel: " .
                        strtoupper($this->activeFocusContext),
                );
                return true;
            }

            // =========================================================================
            // 📁 2. INTERCEPT DATA WHEN SIDEBAR FOCUS IS LOCKED (CTRL HELD UP)
            // =========================================================================
            $sidebarOpen = method_exists($editor, "isCtrlKeyPressed")
                ? $editor->isCtrlKeyPressed()
                : false;

            if ($sidebarOpen || $this->activeFocusContext === "sidebar") {
                $items = method_exists($editor, "getSidebarItems")
                    ? $editor->getSidebarItems()
                    : [];
                if (
                    empty($items) &&
                    method_exists($editor, "refreshSidebarTree")
                ) {
                    $editor->refreshSidebarTree();
                    $items = $editor->getSidebarItems();
                }

                $currentRow = method_exists($editor, "getSidebarFocusRow")
                    ? $editor->getSidebarFocusRow()
                    : 0;

                switch ($codeName) {
                    case "Up":
                    case "Ctrl+Up":
                        if (
                            $currentRow > 0 &&
                            method_exists($editor, "setSidebarFocusRow")
                        ) {
                            $editor->setSidebarFocusRow($currentRow - 1);
                        }
                        return true;

                    case "Down":
                    case "Ctrl+Down":
                        if (
                            $currentRow < count($items) - 1 &&
                            method_exists($editor, "setSidebarFocusRow")
                        ) {
                            $editor->setSidebarFocusRow($currentRow + 1);
                        }
                        return true;

                    // 💡 FIXED: Explicitly group alternative triggers to bypass OS-level hotkey hijacking!
                    case "Enter":
                    case "Space":
                    case "Ctrl+Space":
                    case " ":
                        $targetItem = $items[$currentRow] ?? null;
                        if ($targetItem) {
                            if ($targetItem["is_dir"]) {
                                // A. FOLDER EXPLORATION: Deep dive into subdirectories
                                if (
                                    method_exists($editor, "setSidebarScanRoot")
                                ) {
                                    $editor->setSidebarScanRoot(
                                        $targetItem["path"],
                                    );
                                    $editor->setSidebarFocusRow(0); // Reset selection list position
                                    $editor->refreshSidebarTree();
                                }
                                $state->setEditorStatus(
                                    "Navigated to folder: " .
                                        $targetItem["path"],
                                );
                            } else {
                                // B. FILE SELECTION: Replace scratchpad buffer with target file natively
                                $filePath = $targetItem["path"];
                                if (file_exists($filePath)) {
                                    $state->setEditorFilePath($filePath);
                                    $state->setEditorLines(
                                        explode(
                                            "\n",
                                            file_get_contents($filePath),
                                        ),
                                    );
                                    $state->setEditorRow(0);
                                    $state->setEditorCol(0);
                                    $state->setEditorStatus(
                                        "Loaded document: " .
                                            basename($filePath),
                                    );

                                    // Drop the control panel visible layout mask instantly on item selection
                                    if (
                                        method_exists(
                                            $editor,
                                            "setCtrlKeyPressed",
                                        )
                                    ) {
                                        $editor->setCtrlKeyPressed(false);
                                    }
                                    $this->activeFocusContext = "editor"; // Snap focus back to code view
                                }
                            }
                        }
                        return true;

                    case "Esc":
                        $state->setEditorOpen(false);
                        return true;
                }
                return true;
            }

            // =========================================================================
            // 📝 3. CORE TEXT EDITOR PIPELINE (STANDARD TYPING VIEWS CO-LOCKED)
            // =========================================================================
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

        // =========================================================================
        // ⌨️ 4. CHAR EVENT PIPELINE: Handle Typewriter Stream Entries & Ctrl+Space
        // =========================================================================
        if ($event instanceof CharKeyEvent) {
            $isCtrl =
                ($event->modifiers & \PhpTui\Term\KeyModifiers::CONTROL) ===
                \PhpTui\Term\KeyModifiers::CONTROL;
            $char = strtolower($event->char);

            // 💡 UN-HIJACKABLE INTERCEPTOR: Capture character-level raw Ctrl+Space sequence tokens (\x00) safely
            if ($isCtrl && ($char === " " || $event->char === "\x00")) {
                $sidebarOpen = method_exists($editor, "isCtrlKeyPressed")
                    ? $editor->isCtrlKeyPressed()
                    : false;
                if ($sidebarOpen) {
                    $items = method_exists($editor, "getSidebarItems")
                        ? $editor->getSidebarItems()
                        : [];
                    $currentRow = method_exists($editor, "getSidebarFocusRow")
                        ? $editor->getSidebarFocusRow()
                        : 0;
                    $targetItem = $items[$currentRow] ?? null;

                    if ($targetItem) {
                        if (
                            $targetItem["is_dir"] &&
                            method_exists($editor, "setSidebarScanRoot")
                        ) {
                            $editor->setSidebarScanRoot($targetItem["path"]);
                            $editor->setSidebarFocusRow(0);
                            $editor->refreshSidebarTree();
                        } elseif (file_exists($targetItem["path"])) {
                            $state->setEditorFilePath($targetItem["path"]);
                            $state->setEditorLines(
                                explode(
                                    "\n",
                                    file_get_contents($targetItem["path"]),
                                ),
                            );
                            $state->setEditorRow(0);
                            $state->setEditorCol(0);
                            $state->setEditorStatus(
                                "Loaded file via Ctrl+Space combo: " .
                                    basename($targetItem["path"]),
                            );

                            if (method_exists($editor, "setCtrlKeyPressed")) {
                                $editor->setCtrlKeyPressed(false);
                            }
                            $this->activeFocusContext = "editor";
                        }
                    }
                    return true;
                }
            }
            if (ord($event->char) >= 32 && $event->char !== "\x7f") {
                if ($this->activeFocusContext === "sidebar") {
                    return true; // Stop alphanumeric letters bleed inside file trees list view page modes
                }
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
