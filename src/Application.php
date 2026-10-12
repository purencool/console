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

        // Reactivate hardware mouse coordinate captures directly over raw terminal channels
        fwrite(STDOUT, "\x1b[?1003h\x1b[?1006h");
        fflush(STDOUT);

        $display = DisplayBuilder::default()->build();
        $uiRenderer = new Renderer();

        // Budget exactly 30 Frames Per Second (~33.3ms per cycle)
        $frameBudget = 0.0333;
        $lastFrameTime = microtime(true);

        // Force an initial dirty flag state so the app renders the interface immediately on boot
        if (method_exists($state, "setDirty")) {
            $state->setDirty(true);
        }

        while ($state->isRunning()) {
            $currentTime = microtime(true);
            $deltaTime = $currentTime - $lastFrameTime;

            // Change the layout check to use your new getInvalidateLayout() method name
            $isLayout =
                !method_exists($state, "getInvalidateLayout") ||
                $state->getInvalidateLayout() ||
                $state->isProcessing();

            if ($deltaTime >= $frameBudget && $isLayout) {
                $uiRenderer->render($display, $this->services);
                $lastFrameTime = $currentTime;
                if (method_exists($state, "setInvalidateLayout")) {
                    $state->setInvalidateLayout(false);
                }
            }

            // Use kernel-level stream selection to protect the CPU!
            $read = [STDIN];
            $write = null;
            $except = null;

            // Block for up to 100ms at the kernel level.
            $hasInput = @stream_select($read, $write, $except, 0, 100000);

            if ($hasInput > 0 || $state->isProcessing()) {
                // Input is ready or an AI response spinner is active, process the event stream
                $event = $this->terminal->events()->next();

                if ($event !== null) {
                    $maxScroll = method_exists($state, "getMaxScroll")
                        ? $state->getMaxScroll()
                        : 0;

                    EventHandler::handle(
                        $event,
                        $this->services,
                        $maxScroll,
                        $display,
                    );

                    if (method_exists($state, "setInvalidateLayout")) {
                        $state->setInvalidateLayout(true);
                    }
                }
            }
        }

        // Clean Application Shutdown Sequence
        $display->clear();
        fwrite(STDOUT, "\x1b[?1003l\x1b[?1006l\x1b[?1049l");
        echo "\x1b[?25h"; // Restore native hardware cursor
    }
}
