<?php
namespace App\UI\Component\Keybindings;
use App\State\AppState;
use App\Services\KeyBindingService;
use App\Services\JsonService;

class ChatSubmissionHandler
{
    /** * Handles typing submissions and matches shortcodes seamlessly. */
    public function handle(array $services): bool
    {
        $keyBinding = new KeyBindingService();
        $jsonService = new JsonService();
        $rawInput = $services["state"]->getInputText();
        $processedInput = $keyBinding->replaceCommandNew($rawInput, "\n");
        $promptText = trim($processedInput);
        if ($promptText === "") {
            return false;
        }
        if (!str_starts_with($promptText, "/")) {
            $services["state"]->addChatMessage("You: " . $promptText);
            $services["state"]->setInputText("");
            $services["state"]->setAutoScroll(true);
            try {
                $result = $jsonService->makeCall(
                    $services["state"],
                    "ai",
                    $promptText,
                );
                if (isset($result["success"]) && $result["success"]) {
                    $services["state"]->addChatMessage(
                        "[Agent] " . implode("\n", $result["output"]),
                    );
                } else {
                    $services["state"]->addChatMessage(
                        "[Error] " . implode("\n", $result["output"]),
                    );
                }
            } catch (\Throwable $e) {
                $services["state"]->addChatMessage(
                    "[Error] Invocation target routing failed: " .
                        $e->getMessage(),
                );
            } finally {
                $services["state"]->setProcessing(false);
            }

            if ($promptText === "/ai see") {
                $state->addChatMessage("You: /ai see");
                $state->addChatMessage("[System] Registered AI Services Matrix:");
                $aiServices = $configManager->getServicesByCategory("ai");
                $aiProfile = $state->getDriverProfile("ai");
                $activeId = $aiProfile["id"] ?? "";
                if (empty($aiServices)) {
                $state->addChatMessage(
                " (No services registered in SERVICES_CONFIG)",
                );  return true;  }
                foreach ($aiServices as $id => $meta) {
                    $marker = $id === $activeId ? "➔ [ACTIVE]" : " ";
                    $state->addChatMessage(
                    sprintf(
                    "%s %s (Type: %s, Method: %s, Endpoint: %s)",
                    $marker,
                    $id,
                    $meta["type"] ?? "unknown",
                    $meta["method"] ?? "POST",
                    $meta["endpoint"] ?? "none",  ),  ); }  return true;  }
            if (str_starts_with($promptText, "/ai change ")) {
                $targetService = trim(substr($promptText, strlen("/ai change ")));
                $state->addChatMessage("You: " . $promptText);
                $aiServices = $configManager->getServicesByCategory("ai");
                if (!isset($aiServices[$targetService])) {
                $state->addChatMessage(
                "[Error] Service '{$targetService}' is not defined inside your SERVICES_CONFIG blocks.",
                );  return true;
                }
                $state->setActiveAiService($targetService);
                $state->addChatMessage(
                "[System] Successfully swapped runtime active AI configuration link to: " .  $targetService,
                ); return true;  }
    // Standard fallbacks (help, edit, snippets)
     if ($keyBinding->isHelpCommand($promptText)) {
         $state->addChatMessage("You: " . $promptText);
         $state->addChatMessage("[System] Application Help & Shortcuts:");
         foreach ($keyBinding->getHelpOptions() as $option) {
             $state->addChatMessage($option);
         }
         return true;
     }  if ($keyBinding->isEditCommand($promptText)) {
         $state->addChatMessage("You: " . $promptText);
         $filePath = $keyBinding->extractEditPath($promptText);
         if (empty($filePath)) {
             $filePath = ".config/content.txt";
             $dir = dirname($filePath);
             if (!file_exists($dir)) {
                 @mkdir($dir, 0755, true);
             } / if (!file_exists($filePath)) {
                 @touch($filePath);
                 $state->addChatMessage(
                 "[System] Initialized missing default configuration target at: '{$filePath}'",
                 );
             } // } else {
             if (!file_exists($filePath)) {
             $state->addChatMessage(
             "[Error] File not found at target location: '{$filePath}'. Ensure the file path is correct.",
             );
             return true;
             }
         } //
         $content = @file_get_contents($filePath);
         $state->setEditorFilePath($filePath);
         $state->setEditorLines(explode("\n", (string) $content));
         $state->setEditorOpen(true);
         return true;
     }
     if ($keyBinding->isSnippetCommand($promptText)) {
         $state->addChatMessage("You: " . $promptText);
         $snippetName = $keyBinding->extractSnippetName($promptText);
         if (empty($snippetName) || $snippetName === "list") {
             $state->addChatMessage("[System] Available snippets:");
             foreach (SnippetService::getAvailableList() as $item) {
                 $state->addChatMessage(" - " . $item);
             }
         } else {
             $snippetText = SnippetService::get($snippetName);
             if ($snippetText !== null) {
                 $state->setInputText($snippetText);
             } else {
                 $state->addChatMessage("[Error] Snippet not found.");
             } }  return true;  } return false; }}

            return true;
        }

        }

    }
}
