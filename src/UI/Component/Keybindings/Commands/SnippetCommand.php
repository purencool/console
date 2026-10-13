<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;
use App\Services\KeyBindingService;
use App\Services\SnippetService;

class SnippetCommand implements CommandHandlerInterface
{
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var KeyBindingService $keyBinding */
        $keyBinding = $services["keybindings"];

        $fullPromptContext = "/snippet " . $argument;
        $snippetName = $keyBinding->extractSnippetName($fullPromptContext);

        // Isolate any extra user words typed after the shortcut target
        // Splits the argument space string cleanly (e.g. "c watar" -> ["c", "watar"])
        $argumentParts = explode(" ", trim($argument), 2);
        $lookupName = !empty($argumentParts[0])
            ? strtolower(trim($argumentParts[0]))
            : "";
        $extraTrailingText = isset($argumentParts[1])
            ? trim($argumentParts[1])
            : "";

        // If the query is empty or explicitly matched to list parameters, pull the directory options
        if (empty($lookupName) || $lookupName === "list") {
            $state->addChatMessage("[System] Available snippets:");
            foreach (SnippetService::getAvailableList() as $item) {
                $state->addChatMessage("  - " . $item);
            }
            return true;
        }

        // Retrieve your verified template payload match string
        $snippetText = SnippetService::get($lookupName);

        if ($snippetText !== null) {
            // Append the extra arguments smoothly if they were provided in the stream pool
            $finalCombinedBuffer = $snippetText;
            if ($extraTrailingText !== "") {
                $finalCombinedBuffer .= $extraTrailingText;
            }

            $state->setInputText($finalCombinedBuffer);

            // Move your cursor index position tracking right to the very end of the line
            if (method_exists($state, "setInputCursor")) {
                $state->setInputCursor(mb_strlen($finalCombinedBuffer));
            }
        } else {
            $state->addChatMessage(
                "[Error] Snippet shortcut target pointer '{$lookupName}' not found.",
            );
        }

        return true;
    }
}
