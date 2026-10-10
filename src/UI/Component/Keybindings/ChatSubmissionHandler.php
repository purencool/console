<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;
use App\Services\KeyBindingService;
use App\Services\JsonService;

class ChatSubmissionHandler
{
    /**
     * Processes alphanumeric conversational input prompts and forwards them to active drivers.
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
                // Execute communication tasks using your dynamic pre-compiled drivers registry
                $result = $jsonService->makeCall(
                    $state,
                    "ai",
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
