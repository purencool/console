<?php
namespace App\Services;

use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\KeyCode;

class KeyBindingService
{
    public const ACTION_EXIT = 'exit';
    public const ACTION_FORCE_EXIT = 'force_exit';
    public const ACTION_TOGGLE_TERMINAL = 'toggle_terminal';
    public const ACTION_TOGGLE_SIDEBAR = 'toggle_sidebar';
    public const ACTION_TOGGLE_FOCUS = 'toggle_focus';
    public const ACTION_PASTE = 'paste';

    public const CMD_NEW = '/n';
    public const CMD_HELP = '/';
    public const CMD_PATH = '/path';
    public const CMD_PASTE = '/paste';
    public const CMD_SAVE = '/save';
    
    public const CMD_SKILLS_LOAD = '/skills load';
    public const CMD_SKILLS_SEE = '/skills see';
    public const CMD_SNIPPET = '/snippet';
    public const CMD_EDIT = '/edit';

    public function matchEvent(Event $event): ?string
    {
        if ($event instanceof CodedKeyEvent) {
            if ($event->code === KeyCode::Esc) {
                return self::ACTION_EXIT;
            }
            if ($event->code === KeyCode::Tab) {
                return self::ACTION_TOGGLE_FOCUS;
            }
        }

        if ($event instanceof CharKeyEvent) {
            $isCtrl = ($event->modifiers & KeyModifiers::CONTROL) === KeyModifiers::CONTROL;
            $char = strtolower($event->char);

            if ($event->char === "\x11" || ($isCtrl && $char === 'q')) {
                return self::ACTION_FORCE_EXIT;
            }
            if ($event->char === "\x14" || ($isCtrl && $char === 't')) {
                return self::ACTION_TOGGLE_TERMINAL;
            }
            if ($event->char === "\x13" || ($isCtrl && $char === 's')) {
                return self::ACTION_TOGGLE_SIDEBAR;
            }
            if ($event->char === "\x16" || ($isCtrl && $char === 'v')) {
                return self::ACTION_PASTE;
            }
        }

        return null;
    }

    public function isCommandNew(string $input): bool { return str_ends_with($input, self::CMD_NEW); }
    public function replaceCommandNew(string $input, string $replacement = "\n"): string { return str_replace(self::CMD_NEW, $replacement, $input); }
    public function removeCommandNew(string $input): string { return substr($input, 0, -strlen(self::CMD_NEW)); }
    
    public function isHelpCommand(string $input): bool { return trim($input) === self::CMD_HELP; }
    public function isPasteCommand(string $input): bool { return trim($input) === self::CMD_PASTE; }
    
    public function isPathCommand(string $input): bool { return str_starts_with(trim($input), self::CMD_PATH . ' '); }
    public function extractPathFromCommand(string $input): string { return trim(substr(trim($input), strlen(self::CMD_PATH))); }
    
    public function isSaveCommand(string $input): bool { return str_starts_with(trim($input), self::CMD_SAVE); }
    public function extractSavePathFromCommand(string $input): string { return trim(substr(trim($input), strlen(self::CMD_SAVE))); }
    
    public function isSkillsLoadCommand(string $input): bool { return str_starts_with(trim($input), self::CMD_SKILLS_LOAD); }
    public function isSkillsSeeCommand(string $input): bool { return trim($input) === self::CMD_SKILLS_SEE; }

    public function isEditCommand(string $input): bool { return str_starts_with(trim($input), self::CMD_EDIT . ' '); }
    public function extractEditPath(string $input): string { return trim(substr(trim($input), strlen(self::CMD_EDIT))); }

    public function isSnippetCommand(string $input): bool { 
        $trim = trim($input);
        return str_starts_with($trim, self::CMD_SNIPPET) || str_starts_with($trim, "/s ") || $trim === "/s"; 
    }
    public function extractSnippetName(string $input): string { 
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
            "  Ctrl+Q : Force Exit",
            "  Ctrl+T : Toggle Terminal Drawer",
            "  Ctrl+S : Toggle Sidebar",
            "  Ctrl+V : Paste from system clipboard",
            "  Tab    : Toggle Focus (Input/Terminal)",
            "  Esc    : Exit application",
            "",
            "Commands:",
            "  /n           : Insert newline in prompt",
            "  /paste       : Safely paste multi-line text from system clipboard",
            "  /save <file> : Save the current chat log to a file",
            "  /path <path> : Enter a valid file/folder path to scan it",
            "  /skills load <system|user> <f1.md> [f2.md]: Load up to 2 skills",
            "  /skills see  : Show currently loaded skills",
            "  /s <name>    : Load a text snippet (e.g., '/s c' for correction)",
            "  /s list      : List all available text snippets",
            "  /edit <path> : Open a file in the full-screen text editor",
            "  /            : Show this help menu"
        ];
    }
}
