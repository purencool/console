<?php
namespace App\UI;

use App\State\AppState;
use App\Services\LlmService;
use App\Services\DirectoryScannerService;
use PhpTui\Term\Terminal;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\Event\MouseEvent;
use PhpTui\Term\KeyModifiers;
use PhpTui\Term\KeyCode;
use ValueError;
use UnexpectedValueException;

/**
 *
 */
class EventHandler
{
    /**
     * @param (callable(): mixed) $logger
     * @param (callable(): mixed) $artisanRunner
     *
     * @throws ValueError
     * @throws UnexpectedValueException
     */
    public static function handle(
        Event $event,
        Terminal $terminal,
        AppState $state,
        LlmService $llmService,
        int $maxScroll,
        callable $logger,
        callable $artisanRunner,
    ): bool {
        if ($event instanceof CodedKeyEvent) {
            if ($event->code === KeyCode::Esc) {
                $state->stop();
            } elseif ($event->code === KeyCode::Tab) {
                if ($state->isTerminalDrawerOpen()) {
                    $state->toggleInputFocus();
                    return true;
                }
            } elseif ($event->code === KeyCode::Up) {
                if (
                    $state->isTerminalDrawerOpen() &&
                    $state->getInputFocus() === "terminal"
                ) {
                    $state->setTerminalScrollOffset(
                        max(0, $state->getTerminalScrollOffset() - 1),
                    );
                } else {
                    $state->setScrollOffset(
                        max(0, $state->getScrollOffset() - 1),
                    );
                    $state->setAutoScroll(false);
                }
            } elseif ($event->code === KeyCode::Down) {
                if (
                    $state->isTerminalDrawerOpen() &&
                    $state->getInputFocus() === "terminal"
                ) {
                    $state->setTerminalScrollOffset(
                        $state->getTerminalScrollOffset() + 1,
                    );
                } else {
                    $newOffset = $state->getScrollOffset() + 1;
                    $state->setScrollOffset($newOffset);
                    if ($newOffset >= $maxScroll) {
                        $state->setAutoScroll(true);
                    }
                }
            } elseif ($event->code === KeyCode::Backspace) {
                if (
                    $state->isTerminalDrawerOpen() &&
                    $state->getInputFocus() === "terminal"
                ) {
                    $termInput = $state->getActiveTerminalInput();
                    if (strlen($termInput) > 0) {
                        $state->setActiveTerminalInput(
                            substr($termInput, 0, -1),
                        );
                    }
                } else {
                    $currentInput = $state->getInputText();
                    if (strlen($currentInput) > 0) {
                        if (str_ends_with($currentInput, "/n")) {
                            $state->setInputText(substr($currentInput, 0, -2));
                        } else {
                            $state->setInputText(substr($currentInput, 0, -1));
                        }
                    }
                }
            } elseif ($event->code === KeyCode::Enter) {
                if (
                    $state->isTerminalDrawerOpen() &&
                    $state->getInputFocus() === "terminal"
                ) {
                    $cmd = trim($state->getActiveTerminalInput());
                    if ($cmd !== "") {
                        $state->addTerminalOutput("$ " . $cmd);
                        $output = shell_exec($cmd . " 2>&1");
                        if ($output !== null && trim($output) !== "") {
                            foreach (
                                explode("\n", rtrim($output))
                                as $outLine
                            ) {
                                $state->addTerminalOutput($outLine);
                            }
                        }
                        $state->setActiveTerminalInput("");
                    }
                } else {
                    $rawInput = $state->getInputText();
                    $processedInput = str_replace("/n", "\n", $rawInput);
                    $promptText = trim($processedInput);

                    if ($promptText !== "") {
                        // Check if input is a path to scan
                        if (file_exists($promptText)) {
                            [
                                $success,
                                $resultMsg,
                            ] = DirectoryScannerService::scan($promptText);
                            if ($success) {
                                $state->setScannedPath($promptText);
                                $state->setScannedContext($resultMsg);
                                $state->addChatMessage(
                                    "You: [Scanned Path] " . $promptText,
                                );
                                $state->addChatMessage(
                                    "[System] Successfully scanned '$promptText'. You can now chat about its contents!",
                                );
                            } else {
                                $state->addChatMessage("You: " . $promptText);
                                $state->addChatMessage("[Error] " . $resultMsg);
                            }
                            $state->setInputText("");
                            $state->setAutoScroll(true);
                            return false;
                        }

                        // Regular chat message / prompt execution
                        foreach (explode("\n", $promptText) as $idx => $line) {
                            $prefix = $idx === 0 ? "You: " : "    ";
                            $state->addChatMessage($prefix . $line);
                        }

                        $state->setInputText("");
                        $state->setAutoScroll(true);

                        // Append scanned context if active
                        $fullPrompt = $promptText;
                        if ($state->getScannedContext() !== "") {
                            $fullPrompt =
                                "Context from scanned path (" .
                                $state->getScannedPath() .
                                "):\n" .
                                $state->getScannedContext() .
                                "\n\nUser Question: " .
                                $promptText;
                        }

                        [$cmd, $logMsg] = $llmService->resolveCommand(
                            $fullPrompt,
                        );
                        $state->addChatMessage($logMsg);
                        $logger("Executing: $cmd");

                        $output = shell_exec($cmd . " 2>&1");
                        if ($output === null || trim($output) === "") {
                            $state->addChatMessage(" > (No output returned)");
                        } else {
                            $lines = explode("\n", rtrim($output));
                            if (count($lines) > 200) {
                                $lines = array_slice($lines, -200);
                            }
                            foreach ($lines as $line) {
                                $state->addChatMessage($line);
                            }
                        }
                    }
                }
            }
        }

        if ($event instanceof CharKeyEvent) {
            $isCtrlC =
                $event->char === "\x03" ||
                (strtolower($event->char) === "c" &&
                    ($event->modifiers & KeyModifiers::CONTROL) ===
                        KeyModifiers::CONTROL);
            if ($isCtrlC) {
                exit(1);
            }

            $isCtrlT =
                $event->char === "\x14" ||
                (strtolower($event->char) === "t" &&
                    ($event->modifiers & KeyModifiers::CONTROL) ===
                        KeyModifiers::CONTROL);
            if ($isCtrlT) {
                $state->toggleTerminalDrawer();
                return false;
            }

            $isCtrlS =
                $event->char === "\x13" ||
                (strtolower($event->char) === "s" &&
                    ($event->modifiers & KeyModifiers::CONTROL) ===
                        KeyModifiers::CONTROL);
            if ($isCtrlS) {
                $state->toggleSidebar();
                return false;
            }

            if (ord($event->char) >= 32) {
                if (
                    $state->isTerminalDrawerOpen() &&
                    $state->getInputFocus() === "terminal"
                ) {
                    $state->appendActiveTerminalInput($event->char);
                } else {
                    $state->appendInputText($event->char);
                }
            }
        }

        if ($event instanceof MouseEvent) {
            $kindName = is_object($event->kind)
                ? $event->kind->name ?? ""
                : (string) $event->kind;

            if (stripos($kindName, "scrollup") !== false) {
                if ($state->isTerminalDrawerOpen()) {
                    $state->setTerminalScrollOffset(
                        max(0, $state->getTerminalScrollOffset() - 3),
                    );
                } else {
                    $state->setScrollOffset(
                        max(0, $state->getScrollOffset() - 3),
                    );
                    $state->setAutoScroll(false);
                }
                return true;
            }
            if (stripos($kindName, "scrolldown") !== false) {
                if ($state->isTerminalDrawerOpen()) {
                    $state->setTerminalScrollOffset(
                        $state->getTerminalScrollOffset() + 3,
                    );
                } else {
                    $newOffset = $state->getScrollOffset() + 3;
                    $state->setScrollOffset($newOffset);
                    if ($newOffset >= $maxScroll) {
                        $state->setAutoScroll(true);
                    }
                }
                return true;
            }
        }

        return false;
    }
}
