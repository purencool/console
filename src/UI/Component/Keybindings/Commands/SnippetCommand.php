<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;
use App\Services\SnippetService;

class SnippetCommand implements CommandHandlerInterface
{
    /**
     * Processes snippet queries and expands shortcuts inline.
     */
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];

        // entirely removing the missing KeyBindingService extraction method dependency!
        $argumentParts = explode(" ", trim($argument), 2);
        $lookupName = isset($argumentParts[0])
            ? strtolower(trim($argumentParts[0]))
            : "";
        $extraTrailingText = isset($argumentParts[1])
            ? trim($argumentParts[1])
            : "";

        // If the query is empty or explicitly matched to list parameters, pull the directory options
        if ($lookupName === "" || $lookupName === "list") {
            $state->addChatMessage("[System] Available snippets:");
            foreach (SnippetService::getAvailableList() as $item) {
                $state->addChatMessage("  - " . $item);
            }
            return true;
        }

        // Retrieve your verified template payload match string
        $snippetText = SnippetService::get($lookupName);

        if ($snippetText !== null) {
            // Append the extra arguments smoothly if they were provided in the stream pool (e.g. /s c watar)
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
