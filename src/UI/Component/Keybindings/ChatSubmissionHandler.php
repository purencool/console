<?php
namespace App\UI\Component\Keybindings;

use App\State\AppState;
use App\Services\KeyBindingService;
use App\UI\Component\Keybindings\Commands\HelpCommand;
use App\UI\Component\Keybindings\Commands\AiSeeCommand;
use App\UI\Component\Keybindings\Commands\AiChangeCommand;
use App\UI\Component\Keybindings\Commands\SkillsCommand;
use App\UI\Component\Keybindings\Commands\ClearCommand;
use App\UI\Component\Keybindings\Commands\EditCommand;
use App\UI\Component\Keybindings\Commands\SnippetCommand;
use App\UI\Component\Keybindings\Commands\StandardChatProcessor;
use App\UI\Component\Keybindings\Commands\CommandHandlerInterface;

class ChatSubmissionHandler
{
    /**
     * @var array<string, CommandHandlerInterface> Central routing map
     */
    private array $commandMap = [];

    /**
     * @var StandardChatProcessor Central standard conversation engine
     */
    private StandardChatProcessor $chatProcessor;

    public function __construct()
    {
        $this->chatProcessor = new StandardChatProcessor();

        $this->commandMap = [
            "/" => new HelpCommand(),
            "/skills" => new SkillsCommand(),
            "/clear" => new ClearCommand(),
            "/ai see" => new AiSeeCommand(),
            "/ai" => new AiChangeCommand(),
            "/edit" => new EditCommand(),
            "/snippet" => new SnippetCommand(),
        ];
    }

    /**
     * Processes alphanumeric conversational input prompts, shortcodes, and system commands.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(array $services): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var KeyBindingService $keyBinding */
        $keyBinding = $services["keybindings"];

        $rawInput = $state->getInputText();
        $processedInput = $keyBinding->replaceCommandNew($rawInput, "\n");
        $promptText = trim($processedInput);

        if ($promptText === "") {
            return false;
        }

        $handled = false;

        if (!str_starts_with($promptText, "/")) {
            // 💡 Processing state management is handled internally by StandardChatProcessor!
            $handled = $this->chatProcessor->execute($services, $promptText);
        } else {
            // Clear entry box buffer natively upon any command catch match
            $state->setInputText("");

            // Exact match checks (e.g., /, /skills, /clear, /ai see)
            if (isset($this->commandMap[$promptText])) {
                $handled = $this->commandMap[$promptText]->execute(
                    $services,
                    "",
                );
            } else {
                // Clear array index indices [0] and [1] applied to remove parser noise
                $parts = explode(" ", $promptText, 2);
                $baseCommand = isset($parts[0]) ? $parts[0] : "";
                $argument = isset($parts[1]) ? $parts[1] : "";

                // Intercept composite prefix tokens (such as /ai change)
                if (
                    $baseCommand === "/ai" &&
                    str_starts_with($argument, "change ")
                ) {
                    $handled = $this->commandMap["/ai"]->execute(
                        $services,
                        substr($argument, 7),
                    );
                } elseif (isset($this->commandMap[$baseCommand])) {
                    $handled = $this->commandMap[$baseCommand]->execute(
                        $services,
                        $argument,
                    );
                } else {
                    $state->addChatMessage(
                        "[Error] Unknown or malformed shortcode system action command string.",
                    );
                    $handled = true;
                }
            }
        }

        // Handle layout changes safely without blocking active processing states
        if (method_exists($state, "setInvalidateLayout")) {
            $state->setInvalidateLayout(true);
        }

        return $handled;
    }
}
