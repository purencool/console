<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;
use App\Services\LlmService;
use App\Services\KeyBindingService;
use App\Services\SnippetService;

class ChatSubmissionHandler
{
    public function handle(
        AppState $state,
        LlmService $llmService,
        callable $logger,
    ): bool {
        $keyBinding = new KeyBindingService();
        $rawInput = $state->getInputText();
        $processedInput = $keyBinding->replaceCommandNew($rawInput, "\n");
        $promptText = trim($processedInput);

        if ($promptText !== "") {
            $state->addChatMessage("You: " . $promptText);
            $state->setInputText("");
            $state->setAutoScroll(true);

            if ($keyBinding->isHelpCommand($promptText)) {
                $state->addChatMessage(
                    "[System] Application Help & Shortcuts:",
                );
                foreach ($keyBinding->getHelpOptions() as $option) {
                    $state->addChatMessage($option);
                }
                return true;
            }

            if ($keyBinding->isEditCommand($promptText)) {
                $filePath = $keyBinding->extractEditPath($promptText);
                if (empty($filePath)) {
                    $state->addChatMessage(
                        "[Error] Provide a file path to edit. Example: /edit content.txt",
                    );
                } else {
                    $content = file_exists($filePath)
                        ? file_get_contents($filePath)
                        : "";
                    $state->setEditorFilePath($filePath);
                    $state->setEditorLines(explode("\n", $content));
                    $state->setEditorOpen(true);
                }
                return true;
            }

            if ($keyBinding->isSnippetCommand($promptText)) {
                $snippetName = $keyBinding->extractSnippetName($promptText);
                if (empty($snippetName) || $snippetName === "list") {
                    $state->addChatMessage("[System] Available snippets:");
                    $available = SnippetService::getAvailableList();
                    foreach ($available as $item) {
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

            if ($keyBinding->isSkillsSeeCommand($promptText)) {
                $sys = implode(", ", $state->getLoadedSystemSkills()) ?: "None";
                $usr = implode(", ", $state->getLoadedUserSkills()) ?: "None";
                $state->addChatMessage("[System] Loaded System Skills: $sys");
                $state->addChatMessage("[System] Loaded User Skills: $usr");
                return true;
            }

            $state->setProcessing(true);
            $logger("Sending query to LLM: " . $promptText);

            try {
                $response = $llmService->chat($promptText, $state);
                $state->addChatMessage("[Agent] " . $response);
            } catch (\Throwable $e) {
                $state->addChatMessage("[Error] " . $e->getMessage());
            } finally {
                $state->setProcessing(false);
            }
        }
        return true;
    }
}
