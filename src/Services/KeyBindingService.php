<?php
namespace App\Services;

use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;

class KeyBindingService
{
    public const ACTION_EXIT = "exit";
    public const ACTION_FORCE_EXIT = "force_exit";
    public const ACTION_TOGGLE_TERMINAL = "toggle_terminal";
    public const ACTION_TOGGLE_SIDEBAR = "toggle_sidebar";
    public const ACTION_TOGGLE_FOCUS = "toggle_focus";
    public const ACTION_PASTE = "paste";
    public const ACTION_HISTORY_UP = "history_up";
    public const ACTION_HISTORY_DOWN = "history_down";
    public const ACTION_SCROLL_PAGE_UP = "scroll_page_up";
    public const ACTION_SCROLL_PAGE_DOWN = "scroll_page_down";
    public const ACTION_EDITOR_SELECT_BLOCK = "editor_select_block";
    public const ACTION_EDITOR_TOGGLE_SIDEBAR = "editor_toggle_sidebar";
    public const ACTION_EDITOR_EXECUTE_SELECT = "editor_execute_select";

    /**
     * Matches raw terminal events into explicit action keywords.
     */
    public function matchEvent(Event $event): ?string
    {
        // =========================================================================
        // 1. Handle structural Coded keys (Esc, Tab, Arrows, Enter, Space)
        // =========================================================================
        if ($event instanceof CodedKeyEvent) {
            $name = method_exists($event->code, "name")
                ? $event->code->name()
                : $event->code->name ?? "";

            if (
                $name === "Enter" ||
                $name === "Space" ||
                $event->code === KeyCode::Enter
            ) {
                return self::ACTION_EDITOR_EXECUTE_SELECT;
            }
            if ($event->code === KeyCode::Esc || $name === "Esc") {
                return self::ACTION_EXIT;
            }
            if ($event->code === KeyCode::Tab || $name === "Tab") {
                return self::ACTION_TOGGLE_FOCUS;
            }
            if ($event->code === KeyCode::Up || $name === "Up") {
                return self::ACTION_HISTORY_UP;
            }
            if ($event->code === KeyCode::Down || $name === "Down") {
                return self::ACTION_HISTORY_DOWN;
            }
            if ($event->code === KeyCode::PageUp || $name === "PageUp") {
                return self::ACTION_SCROLL_PAGE_UP;
            }
            if ($event->code === KeyCode::PageDown || $name === "PageDown") {
                return self::ACTION_SCROLL_PAGE_DOWN;
            }
        }

        if ($event instanceof CharKeyEvent) {
            $isCtrl =
                ($event->modifiers & KeyModifiers::CONTROL) ===
                KeyModifiers::CONTROL;
            $char = strtolower($event->char);

            if (
                $char === " " ||
                $char === "" ||
                $event->char === "\x00" ||
                $event->char === " "
            ) {
                return self::ACTION_EDITOR_EXECUTE_SELECT;
            }

            // Un-hijackable Mac arrow key overrides
            if ($isCtrl && ($char === "k" || $event->char === "\x0b")) {
                return self::ACTION_HISTORY_UP;
            }
            if ($isCtrl && ($char === "j" || $event->char === "\x0a")) {
                return self::ACTION_HISTORY_DOWN;
            }

            // Other global shortcut constants
            if (($char === "q" && $isCtrl) || $event->char === "\x11") {
                return self::ACTION_FORCE_EXIT;
            }
            if (($char === "t" && $isCtrl) || $event->char === "\x14") {
                return self::ACTION_TOGGLE_TERMINAL;
            }
            if (($char === "s" && $isCtrl) || $event->char === "\x13") {
                return self::ACTION_TOGGLE_SIDEBAR;
            }
            if (($char === "v" && $isCtrl) || $event->char === "\x16") {
                return self::ACTION_PASTE;
            }
            if (($char === "b" && $isCtrl) || $event->char === "\x02") {
                return self::ACTION_EDITOR_SELECT_BLOCK;
            }
            if (($char === "o" && $isCtrl) || $event->char === "\x0f") {
                return self::ACTION_EDITOR_TOGGLE_SIDEBAR;
            }
        }

        return null;
    }
}
