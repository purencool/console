<?php
namespace App;

use App\State\AppState;
use App\Services\LlmService;
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

    /**
     *
     */
    public function __construct(LlmService $llmService)
    {
        $this->state = new AppState();
        $this->llmService = $llmService;
    }

    /**
     *
     */
    private function log(string $message): void
    {
        $logFile = __DIR__ . "/../error.log";
        $timestamp = date("Y-m-d H:i:s");
        file_put_contents(
            $logFile,
            "[$timestamp] DEBUG: $message\n",
            FILE_APPEND,
        );
    }

    /**
     *
     */
    public function run(): void
    {
        $this->terminal = TerminalSetup::initialize();
        $display = DisplayBuilder::default()->build();

        while ($this->state->isRunning()) {
            $termWidth = $display->viewportArea()->width;
            $leftPaneWidth = (int) ($termWidth * 0.7);
            $innerWidth = max(1, $leftPaneWidth - 2);

            $wrappedInputLines = \App\Support\ReflectionHelper::wrapText(
                $this->state->getInputText() . "",
                $innerWidth,
            );
            $wrappedChatLog = [];
            foreach ($this->state->getChatLog() as $logLine) {
                foreach (
                    \App\Support\ReflectionHelper::wrapText(
                        $logLine,
                        $innerWidth,
                    )
                    as $wl
                ) {
                    $wrappedChatLog[] = $wl;
                }
            }

            $inputBoxHeight = min(15, max(3, count($wrappedInputLines) + 2));
            $terminalDrawerHeight = $this->state->isTerminalDrawerOpen()
                ? (int) ($display->viewportArea()->height * 0.35)
                : 0;
            $visibleChatHeight = max(
                5,
                $display->viewportArea()->height -
                    $inputBoxHeight -
                    $terminalDrawerHeight -
                    2,
            );
            $totalLines = count($wrappedChatLog);
            $maxScroll = max(0, $totalLines - $visibleChatHeight);

            if ($this->state->isAutoScroll()) {
                $this->state->setScrollOffset($maxScroll);
            } else {
                $this->state->setScrollOffset(
                    min(max(0, $this->state->getScrollOffset()), $maxScroll),
                );
            }

            Renderer::render($display, $this->state, $maxScroll);

            while (null !== ($event = $this->terminal->events()->next())) {
                $skipCycle = EventHandler::handle(
                    $event,
                    $this->terminal,
                    $this->state,
                    $this->llmService,
                    $maxScroll,
                    fn($msg) => $this->log($msg),
                    fn() => $this->executeArtisan(),
                );

                if ($skipCycle) {
                    continue 2;
                }
            }
            usleep(10000);
        }
        $display->clear();
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

        $this->log("Executing: $command");
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
