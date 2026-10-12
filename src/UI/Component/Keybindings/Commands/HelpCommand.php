<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;
use App\Services\KeyBindingService;
use App\Services\TerminalLayoutService;

class HelpCommand implements CommandHandlerInterface
{
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var KeyBindingService $keyBinding */
        $keyBinding = $services["keybindings"];
        /** @var TerminalLayoutService $layoutService */
        $layoutService = $services["layout"];

        $helpOptions = $keyBinding->getHelpOptions();
        $incomingSize = $layoutService->calculateMessageLinesSize($helpOptions);

        $state->addChatMessage("[System] Application Help & Shortcuts:");
        foreach ($helpOptions as $option) {
            $state->addChatMessage($option);
        }

        $state->setAutoScroll(false);
        $state->setScrollOffset($state->getScrollOffset() + $incomingSize);

        return true;
    }
}
