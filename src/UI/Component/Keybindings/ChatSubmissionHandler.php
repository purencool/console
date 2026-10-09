<?php
namespace App\UI\Component\Keybindings;
use App\State\AppState;
use App\Services\KeyBindingService;
use App\Services\SnippetService;
use App\Services\ConfigManager;
use App\Services\JsonService;
class ChatSubmissionHandler
{
    public function handle(AppState $state, callable $logger): bool
    {
        $keyBinding = new KeyBindingService();
        $configManager = new ConfigManager();
        $jsonService = new JsonService();
        $rawInput = $state->getInputText();
        $processedInput = $keyBinding->replaceCommandNew($rawInput, "\n");
        $promptText = trim($processedInput);
        if ($promptText === "") {
            return false;
        }

        if ($promptText === "/ai see") {
            $state->addChatMessage("You: /ai see");
            $state->addChatMessage("[System] Registered AI Services Matrix:");
            $aiServices = $configManager->getServicesByCategory("ai");
            $activeService = $state->getActiveAiService();
            if (empty($aiServices)) {
                $state->addChatMessage(
                    "  (No services registered in SERVICES_CONFIG)",
                );
                return true;
            }
            foreach ($aiServices as $id => $meta) {
                $marker = $id === $activeService ? "➔ [ACTIVE]" : "  ";
                $state->addChatMessage(
                    sprintf(
                        "%s %s (Type: %s, Method: %s, Endpoint: %s)",
                        $marker,
                        $id,
                        $meta["type"] ?? "unknown",
                        $meta["method"] ?? "POST",
                        $meta["endpoint"] ?? "none",
                    ),
                );
            }
            return true;
        }

        if (str_starts_with($promptText, "/ai change ")) {
            $targetService = trim(substr($promptText, strlen("/ai change ")));
            $state->addChatMessage("You: " . $promptText);
            $aiServices = $configManager->getServicesByCategory("ai");
            if (!isset($aiServices[$targetService])) {
                $state->addChatMessage(
                    "[Error] Service '{$targetService}' is not defined inside your SERVICES_CONFIG .env blocks.",
                );
                return true;
            }
            $state->setActiveAiService($targetService);
            $state->addChatMessage(
                "[System] Successfully swapped runtime active AI configuration link to: " .
                    $targetService,
            );
            return true;
        }
        // Standard fallbacks (help, edit, snippets)
        if ($keyBinding->isHelpCommand($promptText)) {
            $state->addChatMessage("You: " . $promptText);
            $state->addChatMessage("[System] Application Help & Shortcuts:");
            foreach ($keyBinding->getHelpOptions() as $option) {
                $state->addChatMessage($option);
            }
            return true;
        }
        if ($keyBinding->isEditCommand($promptText)) {
            $state->addChatMessage("You: " . $promptText);
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
            $state->addChatMessage("You: " . $promptText);
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

        if (!str_starts_with($promptText, "/")) {
            $state->addChatMessage("You: " . $promptText);
            $state->setInputText("");
            $state->setAutoScroll(true);
            $activeService = $state->getActiveAiService();
            $state->setProcessing(true);
            $logger(
                "Routing request dynamically via driver strategy: " .
                    $activeService,
            );
            try {
                $result = $jsonService->makeCall(
                    "ai",
                    $activeService,
                    "llm_chat",
                    $promptText,
                );
                if (isset($result["success"]) && $result["success"]) {
                    $state->addChatMessage(
                        "[Agent] " . implode("\n", $result["output"]),
                    );
                } else {
                    $state->addChatMessage(
                        "[Error] " . implode("\n", $result["output"]),
                    );
                }
            } catch (\Throwable $e) {
                $state->addChatMessage(
                    "[Error] Invocation target routing failed: " .
                        $e->getMessage(),
                );
            } finally {
                $state->setProcessing(false);
            }
            return true;
        }
        return false;
    }
}
