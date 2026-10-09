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

        // Handle /n multiline mapping transformations safely
        $processedInput = $keyBinding->replaceCommandNew($rawInput, "\n");
        $promptText = trim($processedInput);

        if ($promptText === "") {
            return false;
        }

        // Output input buffer directly to the local terminal view panel
        $state->addChatMessage("You: " . $promptText);
        $state->setInputText("");
        $state->setAutoScroll(true);

        // --- COMMAND ROUTING PARSING MATRIX ---

        if ($keyBinding->isHelpCommand($promptText)) {
            $state->addChatMessage("[System] Application Help & Shortcuts:");
            foreach ($keyBinding->getHelpOptions() as $option) {
                $state->addChatMessage($option);
            }
            return true;
        }

        if ($keyBinding->isPathCommand($promptText)) {
            $path = $keyBinding->extractPathFromCommand($promptText);
            if (empty($path) || !file_exists($path)) {
                $state->addChatMessage(
                    "[Error] Provided target path is invalid or non-existent.",
                );
            } else {
                $state->setScannedPath($path);
                $state->addChatMessage(
                    "[System] Successfully updated scan tracking root path target to: " .
                        realpath($path),
                );
            }
            return true;
        }

        if ($keyBinding->isSaveCommand($promptText)) {
            $path = $keyBinding->extractSavePathFromCommand($promptText);
            if (empty($path)) {
                $path = "chat_export_" . date("Ymd_His") . ".txt";
            }
            $logData = implode("\n", $state->getChatLog());
            if (@file_put_contents($path, $logData) !== false) {
                $state->addChatMessage(
                    "[System] Chat logs successfully written to disk filename path target: " .
                        $path,
                );
            } else {
                $state->addChatMessage(
                    "[Error] Failed to write chat logs to filesystem target path context location.",
                );
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
                    ? @file_get_contents($filePath)
                    : "";
                $state->setEditorFilePath($filePath);
                $state->setEditorLines(explode("\n", (string) $content));
                $state->setEditorOpen(true);
            }
            return true;
        }

        if ($keyBinding->isSnippetCommand($promptText)) {
            $snippetName = $keyBinding->extractSnippetName($promptText);
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

        if ($keyBinding->isSkillsSeeCommand($promptText)) {
            $sys = method_exists($state, "getLoadedSystemSkills")
                ? implode(", ", $state->getLoadedSystemSkills())
                : "None";
            $usr = method_exists($state, "getLoadedUserSkills")
                ? implode(", ", $state->getLoadedUserSkills())
                : "None";
            $state->addChatMessage("[System] Loaded System Skills: $sys");
            $state->addChatMessage("[System] Loaded User Skills: $usr");
            return true;
        }

        // --- CORE BACKEND LLM CALL BACKBONE ---
        $state->setProcessing(true);
        $logger("Sending query to LLM: " . $promptText);

        try {
            $response = $llmService->chat($promptText, $state);
            $state->addChatMessage("[Agent] " . $response);
        } catch (\Throwable $e) {
            $state->addChatMessage(
                "[Error] Exception execution block: " . $e->getMessage(),
            );
        } finally {
            $state->setProcessing(false);
        }

        return true;
    }
}
