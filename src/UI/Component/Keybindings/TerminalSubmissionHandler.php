<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;

class TerminalSubmissionHandler
{
    /**
     * Captures and executes system command strings typed inside the inline terminal drawer viewport pane.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(array $services): bool
    {
        /** @var AppState $state */
        $state = $services["state"];

        // Only intercept inputs if the terminal window drawer layer is visible and focus is locked to it
        if (
            !$state->isTerminalDrawerOpen() ||
            $state->getInputFocus() !== "terminal"
        ) {
            return false;
        }

        $rawInput = $state->getActiveTerminalInput();
        $command = trim($rawInput);

        if ($command !== "") {
            // Commit string line to local terminal session prompt navigation history arrays
            $state->recordTermSubmission($rawInput);
            $state->addTerminalOutput("$ " . $command);

            // Forward execution task securely through your injected shell service registry layer
            $shell = $services["shell"] ?? null;

            // Toggle processing to high-frequency rendering mode if the shell service
            // outputs any animated sequences or takes time to compute
            if (method_exists($state, "setProcessing")) {
                $state->setProcessing(true);
            }

            $output = $shell->execute($command);

            if ($output !== null && trim($output) !== "") {
                foreach (explode("\n", rtrim($output)) as $outputLine) {
                    $state->addTerminalOutput($outputLine);
                }
            }

            // Clean active typing input buffer line
            $state->setActiveTerminalInput("");

            // Relinquish processing mode back to kernel-level idling
            if (method_exists($state, "setProcessing")) {
                $state->setProcessing(false);
            }
        }

        // Force a dirty state flag toggle right here.
        if (method_exists($state, "getInvalidateLayout")) {
            $state->setInvalidateLayout(true);
        }

        return true;
    }
}
