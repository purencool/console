<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;
use App\Services\ConfigManager;

class AiSeeCommand implements CommandHandlerInterface
{
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var ConfigManager $configManager */
        $configManager = $services["config"];

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
}
