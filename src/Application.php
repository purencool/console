<?php
namespace App;

use App\State\AppState;
use App\Services\LlmService;
use App\Services\LogService;
use App\Support\TerminalSetup;
use App\UI\Renderer;
use App\UI\EventHandler;
use PhpTui\Term\Terminal;
use PhpTui\Tui\DisplayBuilder;

/**
 *
 */
class Application
{
    /** @var ?Terminal */
    private ?Terminal $terminal = null;

    /** @var AppState */
    private AppState $state;

    /** @var LlmService */
    private LlmService $llmService;

    /** @var LogService */
    private LogService $logService;

    /**
     *
     */
    public function __construct(LlmService $llmService, LogService $logService)
    {
        $this->state = new AppState();
        $this->llmService = $llmService;
        $this->logService = $logService;
    }

    /**
     *
     */
    public function run(): void
    {
        echo "\x1b[?25l"; // Hide native hardware cursor
        $this->terminal = TerminalSetup::initialize();
        $display = DisplayBuilder::default()->build();

        while ($this->state->isRunning()) {
            // Let the specialized renderers manage inner bounds and
            // set maximum scroll limits directly in the AppState object.
            Renderer::render(
                $display,
                $this->state,
                $this->state->getMaxScroll(),
            );

            // Pull the cleanly computed boundaries back from your state machine
            $maxScroll = $this->state->getMaxScroll();

            while (null !== ($event = $this->terminal->events()->next())) {
                $skipCycle = EventHandler::handle(
                    $event,
                    $this->terminal,
                    $this->state,
                    $this->llmService,
                    $maxScroll, // Safely forwards the synchronized bounds to mouse listeners
                    fn($msg) => $this->logService->log($msg),
                    fn() => $this->executeArtisan(),
                    $display,
                );

                if ($skipCycle) {
                    continue 2;
                }
            }
            usleep(10000);
        }
        $display->clear();
        // Restore native hardware cursor
        echo "\x1b[?25h";
    }

    /**
     *
     */
    private function executeArtisan(): void
    {
        $options = $this->state->getOptions();
        $command = env_require("CMD_DEFAULT");

        if ($options["cache"]["checked"]) {
            $command = env_require("CMD_CACHE");
        } elseif ($options["migrate"]["checked"]) {
            $command = $options["dryrun"]["checked"]
                ? env_require("CMD_MIGRATE_DRY")
                : env_require("CMD_MIGRATE");
        }

        $this->logService->log("Executing: $command");
        $this->state->addChatMessage("[System] Executing: $command");

        $output = shell_exec($command . " 2>&1");
        if ($output === null || trim($output) === "") {
            $this->state->addChatMessage(" > (No output returned)");
        } else {
            foreach (explode("\n", rtrim($output)) as $line) {
                $this->state->addChatMessage($line);
            }
        }

        $this->state->setAutoScroll(true);
    }
}
