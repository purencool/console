<?php
namespace App\UI\Component\Keybindings\Commands;

interface CommandHandlerInterface
{
    /**
     * Executes the specific command logic.
     *
     * @param array<string, object> $services Centralised system dependency registry
     * @param string $argument The remaining string arguments typed after the command name
     */
    public function execute(array $services, string $argument): bool;
}
