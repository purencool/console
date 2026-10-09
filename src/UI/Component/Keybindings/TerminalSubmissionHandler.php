<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;

class TerminalSubmissionHandler
{
    public function handle(AppState $state): bool
    {
        if (
            $state->isTerminalDrawerOpen() &&
            $state->getInputFocus() === "terminal"
        ) {
            $cmd = trim($state->getActiveTerminalInput());
            if ($cmd !== "") {
                $state->recordTermSubmission($state->getActiveTerminalInput());

                $state->addTerminalOutput("$ " . $cmd);
                $output = shell_exec($cmd . " 2>&1");
                if ($output !== null && trim($output) !== "") {
                    foreach (explode("\n", rtrim($output)) as $outLine) {
                        $state->addTerminalOutput($outLine);
                    }
                }
                $state->setActiveTerminalInput("");
            }
            return true;
        }
        return false;
    }
}