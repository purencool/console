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

        if (empty($snippetName) || $snippetName === "list") {
            $state->addChatMessage("[System] Available snippets:");
            foreach (SnippetService::getAvailableList() as $item) {
                $state->addChatMessage("  - " . $item);
            }
        } else {
            $snippetText = SnippetService::get($snippetName);
            if ($snippetText !== null) {
                $state->setInputText($snippetText);
            } else {
                $state->addChatMessage("[Error] Snippet not found.");
            }
        }
        return true;
    }
}
