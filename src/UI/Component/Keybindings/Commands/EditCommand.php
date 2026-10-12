<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;
use App\Services\KeyBindingService;

class EditCommand implements CommandHandlerInterface
{
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var KeyBindingService $keyBinding */
        $keyBinding = $services["keybindings"];

        // Reconstruct full prompt token line context back to match regex requirements safely
        $fullPromptContext = "/edit " . $argument;
        $filePath = $keyBinding->extractEditPath($fullPromptContext);

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
}
