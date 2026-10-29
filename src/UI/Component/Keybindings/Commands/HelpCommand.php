<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;
use App\Services\KeyBindingService;
use App\Services\TerminalLayoutService;

class HelpCommand implements CommandHandlerInterface
{
    /**
     * Executes the /help command, printing system guides onto the chat
     * view panel framework.
     */
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var KeyBindingService $keyBinding */
        $keyBinding = $services["keybindings"];
        /** @var TerminalLayoutService $layoutService */
        $layoutService = $services["layout"];

        // Define your help options matrix inline to completely remove the missing
        // method call!
        $helpOptions = [
            "=================================================================================================",
            "  /help                     ➔ Displays this workspace instructions guide list menu.",
            "  /clear                    ➔ Wipes the current chat conversational log panel screen.",
            "  /skills                   ➔ Pulls active loaded macro skills capabilities overview lists.",
            "  /edit <file_path>         ➔ Launches full-screen file workspace text editor mode.",
            "  /s <name> [text]          ➔ Expand template text phrases (e.g., /s c text).",
            "  /snippet list             ➔ Displays all your custom dictionary shortcode options.",
            "--------------------------------------------------------------------------------------------------",
            "  Universal macOS Terminal Keybindings Overrides System Hotkeys Map:",
            "  [Hold Ctrl]               ➔ Hold anywhere inside editor to pull project file tree window.",
            "  [Ctrl + J]                ➔ Navigate list options Down cleanly (Mac safe).",
            "  [Ctrl + K]                ➔ Navigate list options Up cleanly (Mac safe).",
            "  [Spacebar]                ➔ Open folder / open file buffer inside your explorer tree.",
            "  [Ctrl + B]                ➔ Activate Visual Block Selection text highlight bounds map.",
            "  [Ctrl + A]                ➔ Open bottom Inline AI Refactor prompt engineering box field.",
            "  [Ctrl + S]                ➔ Persist modifications, writing file stream cleanly to disk storage.",
            "  [Esc]                     ➔ Drops editor open visibility lock, returning to main chat menu.",
            "===================================================================================================",
        ];

        // Safely calculate the layout height using your existing service wrapper
        $incomingSize = $layoutService->calculateMessageLinesSize($helpOptions);

        $state->addChatMessage("[System] Application Help & Shortcuts:");
        foreach ($helpOptions as $option) {
            $state->addChatMessage($option);
        }

        // Retain your high-performance viewport terminal layout scroll tracking
        // calculations
        $state->setAutoScroll(false);
        $state->setScrollOffset($state->getScrollOffset() + $incomingSize);

        return true;
    }
}
