<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;
use App\Services\KeyBindingService;
use App\Services\JsonService;
use App\Services\ConfigManager;
use App\Services\SnippetService;

class ChatSubmissionHandler
{
    /**
     * Processes alphanumeric conversational input prompts, shortcodes, and system commands.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(array $services): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var KeyBindingService $keyBinding */
        $keyBinding = $services["keybindings"];
        /** @var JsonService $jsonService */
        $jsonService = $services["json"];
        /** @var ConfigManager $configManager */
        $configManager = $services["config"];

        $rawInput = $state->getInputText();
        $processedInput = $keyBinding->replaceCommandNew($rawInput, "\n");
        $promptText = trim($processedInput);

        if ($promptText === "") {
            return false;
        }

        if (!str_starts_with($promptText, "/")) {
            $state->addChatMessage("You: " . $promptText);
            $state->setInputText("");
            $state->setAutoScroll(true);
            $state->setProcessing(true);

            try {
                $result = $jsonService->makeCall($services, "ai", $promptText);

                if (isset($result["success"]) && $result["success"]) {
                    $outputLines = $result["output"];
                    /** @var \App\Services\TerminalLayoutService $layoutService */
                    $layoutService = $services["layout"];

                    $incomingSize = $layoutService->calculateMessageLinesSize(
                        $outputLines,
                    );
                    $state->setAutoScroll(false);
                    $state->setScrollOffset(
                        $state->getScrollOffset() + $incomingSize,
                    );

                    $state->addChatMessage(
                        "[Agent] " . implode("\n", $outputLines),
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

        if ($promptText === "/" || $keyBinding->isHelpCommand($promptText)) {
            $state->addChatMessage("You: " . $promptText);
            $state->setInputText("");

            $helpOptions = $keyBinding->getHelpOptions();
            /** @var \App\Services\TerminalLayoutService $layoutService */
            $layoutService = $services["layout"];

            $incomingSize = $layoutService->calculateMessageLinesSize(
                $helpOptions,
            );

            $state->addChatMessage("[System] Application Help & Shortcuts:");
            foreach ($helpOptions as $option) {
                $state->addChatMessage($option);
            }

            $state->setAutoScroll(false);
            $state->setScrollOffset($state->getScrollOffset() + $incomingSize);

            return true;
        }

        if ($promptText === "/skills") {
            $state->addChatMessage("You: " . $promptText);
            $state->setInputText("");
            $state->setAutoScroll(true);
            $state->setProcessing(true);

            try {
                $skillsProfile = $state->getDriverProfile("skills");
                $state->addChatMessage(
                    "[System] Contacting active skills microservice endpoint (" .
                        ($skillsProfile["id"] ?? "default") .
                        ")...",
                );

                $result = $jsonService->makeCall($services, "skills");

                if (isset($result["success"]) && $result["success"]) {
                    $state->addChatMessage(
                        "[Skills Output] " . implode("\n", $result["output"]),
                    );
                } else {
                    $state->addChatMessage(
                        "[Error] " . implode("\n", $result["output"]),
                    );
                }
            } catch (\Throwable $e) {
                $state->addChatMessage(
                    "[Error] Skills target routing failed: " . $e->getMessage(),
                );
            } finally {
                $state->setProcessing(false);
            }
            return true;
        }

        if ($promptText === "/ai see") {
            $state->addChatMessage("You: /ai see");
            $state->setInputText("");

            $aiServices = $configManager->getServicesByCategory("ai");
            $aiProfile = $state->getDriverProfile("ai");
            $activeId = $aiProfile["id"] ?? "";

            if (empty($aiServices)) {
                $state->addChatMessage(
                    "  (No services registered in SERVICES_CONFIG)",
                );
                return true;
            }

            foreach ($aiServices as $id => $meta) {
                $marker = $id === $activeId ? "➔ [ACTIVE]" : "  ";
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
            $state->setInputText("");

            $aiServices = $configManager->getServicesByCategory("ai");

            if (!isset($aiServices[$targetService])) {
                $state->addChatMessage(
                    "[Error] Service '{$targetService}' is not defined inside your SERVICES_CONFIG blocks.",
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

        if ($keyBinding->isEditCommand($promptText)) {
            $state->addChatMessage("You: " . $promptText);
            $state->setInputText("");

            $filePath = $keyBinding->extractEditPath($promptText);

            if (empty($filePath)) {
                $filePath = ".config/content.txt";
                $dir = dirname($filePath);
                if (!file_exists($dir)) {
                    @mkdir($dir, 0755, true);
                }

                if (!file_exists($filePath)) {
                    @touch($filePath);
                    $state->addChatMessage(
                        "[System] Initialized missing default configuration target at: '{$filePath}'",
                    );
                }
            } else {
                if (!file_exists($filePath)) {
                    $state->addChatMessage(
                        "[Error] File not found at target location: '{$filePath}'. Ensure the file path is correct.",
                    );
                    return true;
                }
            }

            $content = @file_get_contents($filePath);
            $state->setEditorFilePath($filePath);
            $state->setEditorLines(explode("\n", (string) $content));
            $state->setEditorOpen(true);

            return true;
        }

        if ($keyBinding->isSnippetCommand($promptText)) {
            $state->addChatMessage("You: " . $promptText);
            $state->setInputText("");

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
        return false;
    }
}
