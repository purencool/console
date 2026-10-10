<?php
namespace App;

use App\Support\TerminalSetup;
use App\UI\Renderer;
use App\UI\EventHandler;
use App\State\AppState;
use App\Services\LogService;
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
        /** @var AppState $state */
        $state = $this->services["state"];
        /** @var LogService $logger */
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
                EventHandler::handle(
                    $event,
                    $this->services,
                    $maxScroll,
                    $display,
                );
                if (!$state->isRunning()) {
                    break 2;
                }
            }

            usleep(10000);
        }

        $display->clear();
        echo "\x1b[?25h"; // Restore native hardware cursor
    }
}
