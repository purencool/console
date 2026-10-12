<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;
use App\Services\ConfigManager;

class AiChangeCommand implements CommandHandlerInterface
{
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var ConfigManager $configManager */
        $configManager = $services["config"];

        $targetService = trim($argument);
        $aiServices = $configManager->getServicesByCategory("ai");

        if (!isset($aiServices[$targetService])) {
            $state->addChatMessage(
                "[Error] Service '{$targetService}' is not defined inside your SERVICES_CONFIG blocks.",
            );
            return true;
        }

        $state->setActiveAiService($targetService);
        $state->addChatMessage(
            "[System] Successfully swapped active AI configuration to: " .
                $targetService,
        );
        return true;
    }
}
