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

    public function __construct(LlmService $llmService, LogService $logService)
    {
        $this->state = new AppState();
        $this->llmService = $llmService;
        $this->logService = $logService;
    }

    public function run(): void
    {
        echo "\x1b[?25l"; // Hide native hardware cursor
        $this->terminal = TerminalSetup::initialize();
        $display = DisplayBuilder::default()->build();

        while ($this->state->isRunning()) {
            Renderer::render(
                $display,
                $this->state,
                $this->state->getMaxScroll(),
            );

            $maxScroll = $this->state->getMaxScroll();

            while (null !== ($event = $this->terminal->events()->next())) {
                // FIXED: Do not drop MouseEvents, let them process inside your sub-handlers!

                // FIXED: Call the handler using its explicit static signature with matching parameters
                EventHandler::handle(
                    $event,
                    $this->terminal,
                    $this->state,
                    $this->llmService,
                    $maxScroll,
                    fn($msg) => $this->logService->log($msg),
                    fn() => $this->executeArtisan(),
                    $display,
                );

                if (!$this->state->isRunning()) {
                    break 2;
                }
            }
            usleep(10000);
        }

        $display->clear();
        echo "\x1b[?25h"; // Restore native hardware cursor
    }

    /**
     * Executes the appropriate artisan command based on app state.
     */
    private function executeArtisan(): void
    {
        $options = $this->state->getOptions();

        $command = $_ENV["CMD_DEFAULT"] ?? "";

        if (!empty($options["cache"]["checked"])) {
            $command = $_ENV["CMD_CACHE"] ?? "";
        } elseif (!empty($options["migrate"]["checked"])) {
            $command = !empty($options["dryrun"]["checked"])
                ? $_ENV["CMD_MIGRATE_DRY"] ?? ""
                : $_ENV["CMD_MIGRATE"] ?? "";
        }

        if (empty($command)) {
            $this->logService->log(
                "Error: Target command environment variable is empty or missing.",
            );
            return;
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
