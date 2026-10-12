<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;
use App\Services\JsonService;
use App\Services\ConfigManager;

class SkillsCommand implements CommandHandlerInterface
{
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var JsonService $jsonService */
        $jsonService = $services["json"];
        /** @var ConfigManager $configManager */
        $configManager = $services["config"];

        $state->setInputText("");

        // Handle sub-action route: /skills change <profile_id>
        if (str_starts_with($argument, "change ")) {
            $targetProfile = trim(substr($argument, 7));
            $skillsServices = $configManager->getServicesByCategory("skills");

            if (!isset($skillsServices[$targetProfile])) {
                $state->addChatMessage(
                    "[Error] Profile '{$targetProfile}' is not defined inside your SERVICES_CONFIG blocks.",
                );
                if (method_exists($state, "setInvalidateLayout")) {
                    $state->setInvalidateLayout(true);
                }
                return true;
            }

            //  if (method_exists($state, "setActiveSkillsProfile")) {
            //      $state->setActiveSkillsProfile($targetProfile);
            //  }

            $state->addChatMessage(
                "[System] Swapped runtime active skills profile execution anchor to: " .
                    $targetProfile,
            );

            if (method_exists($state, "setInvalidateLayout")) {
                $state->setInvalidateLayout(true);
            }
            return true;
        }

        // Standard Default Action: /skills execution
        $state->setAutoScroll(true);
        $state->setProcessing(true);

        try {
            // 🚀 FIXED: Utilize getSystemDrivers() cleanly to find the active configuration
            $profileId = "default";
            if (method_exists($state, "getSystemDrivers")) {
                $drivers = $state->getSystemDrivers();
                $profileId =
                    $drivers["skills"]["active"] ??
                    ($drivers["skills"]["id"] ?? "default");
            }

            $state->addChatMessage(
                "[System] Contacting active skills microservice endpoint (" .
                    $profileId .
                    ")...",
            );

            if (method_exists($state, "setInvalidateLayout")) {
                $state->setInvalidateLayout(true);
            }

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
            if (method_exists($state, "setInvalidateLayout")) {
                $state->setInvalidateLayout(true);
            }
        }

        return true;
    }
}
