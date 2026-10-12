<?php
namespace App\Services;

use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\KeyCode;

class KeyBindingService
{
    // Global Actions
    public const ACTION_EXIT = "exit";
    public const ACTION_FORCE_EXIT = "force_exit";
    public const ACTION_TOGGLE_TERMINAL = "toggle_terminal";
    public const ACTION_TOGGLE_SIDEBAR = "toggle_sidebar";
    public const ACTION_TOGGLE_FOCUS = "toggle_focus";
    public const ACTION_PASTE = "paste";
    public const ACTION_HISTORY_UP = "history_up";
    public const ACTION_HISTORY_DOWN = "history_down";

    // 💡 ADDED: Viewport Scrolling Actions Constants
    public const ACTION_SCROLL_PAGE_UP = "SCROLL_PAGE_UP";
    public const ACTION_SCROLL_PAGE_DOWN = "SCROLL_PAGE_DOWN";

    // Slash Commands
    public const CMD_NEW = "/n";
    public const CMD_HELP = "/";
    public const CMD_PATH = "/path";
    public const CMD_PASTE = "/paste";
    public const CMD_SAVE = "/save";
    public const CMD_SKILLS_LOAD = "/skills load";
    public const CMD_SKILLS_SEE = "/skills see";
    public const CMD_SNIPPET = "/snippet";
    public const CMD_EDIT = "/edit";

    /**
     * Matches raw terminal events into explicit action keywords.
     */
    public function matchEvent(Event $event): ?string
    {
        // 1. Handle explicit Coded keys (Esc, Tab, Arrows, Page Up/Down)
        if ($event instanceof CodedKeyEvent) {
            $name = method_exists($event->code, "name")
                ? $event->code->name()
                : (property_exists($event->code, "name")
                    ? $event->code->name
                    : "");

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

        // 2. Handle text-level stream mutations (Ctrl Combos)
        if ($event instanceof CharKeyEvent) {
            $isCtrl =
                ($event->modifiers & KeyModifiers::CONTROL) ===
                KeyModifiers::CONTROL;
            $char = strtolower($event->char);

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
        }

        return null;
    }

    public function isCommandNew(string $input): bool
    {
        return str_ends_with($input, self::CMD_NEW);
    }
    public function replaceCommandNew(
        string $input,
        string $replacement = "\n",
    ): string {
        return str_replace(self::CMD_NEW, $replacement, $input);
    }
    public function removeCommandNew(string $input): string
    {
        return substr($input, 0, -strlen(self::CMD_NEW));
    }

    public function isHelpCommand(string $input): bool
    {
        return trim($input) === self::CMD_HELP;
    }
    public function isPasteCommand(string $input): bool
    {
        return trim($input) === self::CMD_PASTE;
    }

    public function isPathCommand(string $input): bool
    {
        return str_starts_with(trim($input), self::CMD_PATH . " ");
    }
    public function extractPathFromCommand(string $input): string
    {
        return trim(substr(trim($input), strlen(self::CMD_PATH)));
    }

    public function isSaveCommand(string $input): bool
    {
        return str_starts_with(trim($input), self::CMD_SAVE);
    }
    public function extractSavePathFromCommand(string $input): string
    {
        return trim(substr(trim($input), strlen(self::CMD_SAVE)));
    }

    public function isSkillsLoadCommand(string $input): bool
    {
        return str_starts_with(trim($input), self::CMD_SKILLS_LOAD);
    }
    public function isSkillsSeeCommand(string $input): bool
    {
        return trim($input) === self::CMD_SKILLS_SEE;
    }

    public function isEditCommand(string $input): bool
    {
        $trim = trim($input);
        return $trim === self::CMD_EDIT ||
            str_starts_with($trim, self::CMD_EDIT . " ");
    }
    public function extractEditPath(string $input): string
    {
        $trim = trim($input);
        if ($trim === self::CMD_EDIT) {
            return "";
        }
        return trim(substr($trim, strlen(self::CMD_EDIT)));
    }

    public function isSnippetCommand(string $input): bool
    {
        $trim = trim($input);
        return str_starts_with($trim, self::CMD_SNIPPET) ||
            str_starts_with($trim, "/s ") ||
            $trim === "/s";
    }
    public function extractSnippetName(string $input): string
    {
        $trim = trim($input);
        if (str_starts_with($trim, self::CMD_SNIPPET)) {
            return trim(substr($trim, strlen(self::CMD_SNIPPET)));
        }
        if (str_starts_with($trim, "/s")) {
            return trim(substr($trim, strlen("/s")));
        }
        return "";
    }

    public function getHelpOptions(): array
    {
        return [
            "Key Bindings:",
            "  Ctrl+Q    : Force Exit",
            "  Ctrl+T    : Toggle Terminal Drawer",
            "  Ctrl+S    : Toggle Sidebar",
            "  Ctrl+V    : Paste from system clipboard",
            "  Tab       : Toggle Focus (Input/Terminal)",
            "  PageUp    : Scroll up 15 lines virtual window",
            "  PageDown  : Scroll down 15 lines virtual window",
            "  Esc       : Exit application",
            "",
            "Commands:",
            "  /n           : Insert newline in prompt",
            "  /save <file> : Save the current chat log to a file",
            "  /path <path> : Enter a valid file/folder path to scan it",
            "  /skills load <system|user> <f1.md> [f2.md]: Load up to 2 skills",
            "  /skills see  : Show currently loaded skills",
            "  /s <name>    : Load a text snippet (e.g., '/s c' for correction)",
            "  /s list      : List all available text snippets",
            "  /edit <path> : Open a file in the full-screen text editor",
            "  /            : Show this help menu",
        ];
    }
}
