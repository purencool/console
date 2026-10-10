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
            $output = $shell
                ? $shell->execute($command)
                : shell_exec($command . " 2>&1");

            if ($output !== null && trim($output) !== "") {
                foreach (explode("\n", rtrim($output)) as $outputLine) {
                    $state->addTerminalOutput($outputLine);
                }
            }

            // Clean active typing input buffer line
            $state->setActiveTerminalInput("");
        }

        return true;
    }
}
