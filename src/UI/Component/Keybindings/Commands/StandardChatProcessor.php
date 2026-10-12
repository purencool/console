<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;
use App\Services\JsonService;
use App\Services\TerminalLayoutService;

class StandardChatProcessor implements CommandHandlerInterface
{
    /**
     * Forwards standard conversation text input prompts through the active AI microservice driver.
     */
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var JsonService $jsonService */
        $jsonService = $services["json"];

        $state->addChatMessage("You: " . $argument);
        $state->setInputText("");
        $state->setAutoScroll(true);
        $state->setProcessing(true);

        try {
            $result = $jsonService->makeCall($services, "ai", $argument);

            if (isset($result["success"]) && $result["success"]) {
                $outputLines = $result["output"];
                /** @var TerminalLayoutService $layoutService */
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
                "[Error] Invocation target routing failed: " . $e->getMessage(),
            );
        } finally {
            $state->setProcessing(false);
        }

        return true;
    }
}
