<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;

class EditCommand implements CommandHandlerInterface
{
    /**
     * Executes the /edit command, opening the full-screen file editor canvas cleanly.
     */
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        $editor = method_exists($state, "editor") ? $state->editor() : $state;

        $targetPath = trim($argument);

        // If the path argument is completely blank (e.g. user just typed /edit),
        // default to your scratchpad file path map
        if ($targetPath === "") {
            $targetPath = "./scratchpad.txt";
        }

        // Initialize state variables on your central workspace editor models layers
        $state->setEditorFilePath($targetPath);

        if (file_exists($targetPath)) {
            $fileContent = @file_get_contents($targetPath);
            $lines =
                $fileContent !== false ? explode("\n", $fileContent) : [""];
            $state->setEditorLines($lines);
            $state->setEditorStatus("Loaded file: " . basename($targetPath));
        } else {
            // If the target file doesn't exist yet, seed a fresh buffer so
            // the user can save it later via Ctrl+S
            $state->setEditorLines([""]);
            $state->setEditorStatus(
                "New file buffer initialized: " . basename($targetPath),
            );
        }

        // Reset workspace selection row index and column positions to
        // top-left boundary origins
        $state->setEditorRow(0);
        $state->setEditorCol(0);
        $state->setEditorOpen(true);

        // Trigger an automatic file structure scan tree list refresh on
        // load if the method exists
        if (method_exists($editor, "refreshSidebarTree")) {
            $editor->refreshSidebarTree();
        }

        return true;
    }
}
