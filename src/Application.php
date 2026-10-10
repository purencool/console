<?php
namespace App;

use App\Support\TerminalSetup;
use App\UI\Renderer;
use App\UI\EventHandler;
use PhpTui\Term\Terminal;
use PhpTui\Tui\DisplayBuilder;

class Application
{
    /** @var ?Terminal */
    private ?Terminal $terminal = null;

    /** @var array<string, object> */
    private array $services;

    /**
     * Ingests the centralized system service matrix dependency registries.
     */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    /**
     * Spawns the main terminal application runtime cycle loop.
     */
    public function run(): void
    {
        /** @var \App\State\AppState $state */
        $state = $this->services["state"];
        /** @var \App\Services\LogService $logger */
        $logger = $this->services["log"];

        echo "\x1b[?25l"; // Hide native hardware cursor

        $this->terminal = TerminalSetup::initialize();
        $display = DisplayBuilder::default()->build();
        $uiRenderer = new Renderer();

        while ($state->isRunning()) {
            // Render screen grid coordinates using synchronized maxScroll margins
            $maxScroll = $state->getMaxScroll();
            $uiRenderer->render($display, $this->services);

            while (null !== ($event = $this->terminal->events()->next())) {
                EventHandler::handle($event, $this->services, $maxScroll);
                if (!$state->isRunning()) {
                    break 2;
                }
            }

            usleep(10000);
        }

        $display->clear();
        echo "\x1b[?25h"; // Restore native hardware cursor
    }

    /**
     * Executes the configured background command strings matching selected option layers.
     */
    private function executeArtisan(): void
    {
        /** @var \App\State\AppState $state */
        $state = $this->services["state"];
        /** @var \App\Services\LogService $logger */
        $logger = $this->services["log"];

        $options = $state->getOptions();
        $command = $_ENV["CMD_DEFAULT"] ?? "";

        if (!empty($options["cache"]["checked"])) {
            $command = $_ENV["CMD_CACHE"] ?? "";
        } elseif (!empty($options["migrate"]["checked"])) {
            $command = !empty($options["dryrun"]["checked"])
                ? $_ENV["CMD_MIGRATE_DRY"] ?? ""
                : $_ENV["CMD_MIGRATE"] ?? "";
        }

        if (empty($command)) {
            $logger->log(
                "Error: Target command environment variable is empty or missing.",
            );
            return;
        }

        $logger->log("Executing: $command");
        $state->addChatMessage("[System] Executing: $command");

        $output = shell_exec($command . " 2>&1");
        if ($output === null || trim($output) === "") {
            $state->addChatMessage(" > (No output returned)");
        } else {
            foreach (explode("\n", rtrim($output)) as $line) {
                $state->addChatMessage($line);
            }
        }

        $state->setAutoScroll(true);
    }
}
