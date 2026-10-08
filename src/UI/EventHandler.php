<?php
namespace App\UI;

use App\State\AppState;
use App\Services\LlmService;
use App\Services\DirectoryScannerService;
use App\Services\KeyBindingService;
use App\Services\ClipboardService;
use App\Services\SnippetService;
use PhpTui\Term\Terminal;
use PhpTui\Term\Event;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\Event\CodedKeyEvent;
use PhpTui\Term\Event\MouseEvent;
use PhpTui\Term\KeyCode;
use PhpTui\Term\KeyModifiers;

class EventHandler
{
    public static function handle(
        Event $event,
        Terminal $terminal,
        AppState $state,
        LlmService $llmService,
        int $maxScroll,
        callable $logger,
        callable $artisanRunner,
        ?object $display = null
    ): bool {
        $keyBinding = new KeyBindingService();

        // --- EDITOR MODE HIJACK ---
        if ($state->isEditorOpen()) {
            if ($event instanceof CodedKeyEvent) {
                if ($event->code === KeyCode::Esc) {
                    $state->setEditorOpen(false); // Close Editor
                    return true;
                }
                if ($event->code === KeyCode::Up) {
                    $state->setEditorRow($state->getEditorRow() - 1);
                    $state->setEditorCol($state->getEditorCol()); // recalculates max col
                    return true;
                }
                if ($event->code === KeyCode::Down) {
                    $state->setEditorRow($state->getEditorRow() + 1);
                    $state->setEditorCol($state->getEditorCol());
                    return true;
                }
                if ($event->code === KeyCode::Left) {
                    if ($state->getEditorCol() > 0) {
                        $state->setEditorCol($state->getEditorCol() - 1);
                    } elseif ($state->getEditorRow() > 0) {
                        $state->setEditorRow($state->getEditorRow() - 1);
                        $state->setEditorCol(mb_strlen($state->getEditorLines()[$state->getEditorRow()]));
                    }
                    return true;
                }
                if ($event->code === KeyCode::Right) {
                    if ($state->getEditorCol() < mb_strlen($state->getEditorLines()[$state->getEditorRow()])) {
                        $state->setEditorCol($state->getEditorCol() + 1);
                    } elseif ($state->getEditorRow() < count($state->getEditorLines()) - 1) {
                        $state->setEditorRow($state->getEditorRow() + 1);
                        $state->setEditorCol(0);
                    }
                    return true;
                }
                if ($event->code === KeyCode::Backspace) {
                    $state->backspaceEditorChar();
                    return true;
                }
                if ($event->code === KeyCode::Enter) {
                    $state->enterEditorLine();
                    return true;
                }
            }

            if ($event instanceof CharKeyEvent) {
                $isCtrl = ($event->modifiers & KeyModifiers::CONTROL) === KeyModifiers::CONTROL;
                if ($isCtrl && strtolower($event->char) === 's') {
                    // Save File via Ctrl+S
                    $result = @file_put_contents($state->getEditorFilePath(), implode("\n", $state->getEditorLines()));
                    if ($result !== false) {
                        $state->setEditorStatus("File saved successfully!");
                    } else {
                        $state->setEditorStatus("ERROR: Failed to save file! Check permissions.");
                    }
                    return true;
                }
                
                if (ord($event->char) >= 32 && $event->char !== "\x7f" && !$isCtrl) {
                    $state->insertEditorChar($event->char);
                    return true;
                }
            }
            return true; // Block all other input from reaching chat
        }
        // --- END EDITOR MODE ---

        if ($event instanceof CodedKeyEvent) {
            if ($event->code === KeyCode::Enter) {
                if ($state->isTerminalDrawerOpen() && $state->getInputFocus() === "terminal") {
                    $cmd = trim($state->getActiveTerminalInput());
                    if ($cmd !== "") {
                        $state->addTerminalOutput("$ " . $cmd);
                        $output = shell_exec($cmd . " 2>&1");
                        if ($output !== null && trim($output) !== "") {
                            foreach (explode("\n", rtrim($output)) as $outLine) $state->addTerminalOutput($outLine);
                        }
                        $state->setActiveTerminalInput("");
                    }
                } else {
                    $rawInput = $state->getInputText();
                    $processedInput = $keyBinding->replaceCommandNew($rawInput, "\n");
                    $promptText = trim($processedInput);

                    if ($promptText !== "") {
                        $state->addHistory($promptText);
                        $state->setInputText("");
                        $state->setAutoScroll(true);

                        if ($keyBinding->isHelpCommand($promptText)) {
                            $state->addChatMessage("You: " . $promptText);
                            $state->addChatMessage("[System] Application Help & Shortcuts:");
                            foreach ($keyBinding->getHelpOptions() as $option) $state->addChatMessage($option);
                            return false;
                        }

                        // --- EDIT COMMAND HANDLING ---
                        if ($keyBinding->isEditCommand($promptText)) {
                            $filePath = $keyBinding->extractEditPath($promptText);
                            if (empty($filePath)) {
                                $state->addChatMessage("[Error] Provide a file path to edit. Example: /edit myconfig.json");
                            } else {
                                $content = "";
                                if (file_exists($filePath)) {
                                    $content = file_get_contents($filePath);
                                }
                                $lines = explode("\n", $content);
                                $state->setEditorFilePath($filePath);
                                $state->setEditorLines($lines);
                                $state->setEditorOpen(true);
                            }
                            return false;
                        }

                        if ($keyBinding->isSnippetCommand($promptText)) {
                            $snippetName = $keyBinding->extractSnippetName($promptText);
                            if (empty($snippetName) || $snippetName === 'list') {
                                $available = SnippetService::getAvailableList();
                                $state->addChatMessage("[System] Available snippets:");
                                if (empty($available)) {
                                    $state->addChatMessage("  None");
                                } else {
                                    foreach ($available as $item) $state->addChatMessage("  - " . $item);
                                }
                            } else {
                                $snippetText = SnippetService::get($snippetName);
                                if ($snippetText !== null) $state->setInputText($snippetText);
                                else $state->addChatMessage("[Error] Snippet not found. Type '/s list'.");
                            }
                            return false;
                        }

                        if ($keyBinding->isSkillsSeeCommand($promptText)) {
                            $sys = implode(", ", $state->getLoadedSystemSkills()) ?: "None";
                            $usr = implode(", ", $state->getLoadedUserSkills()) ?: "None";
                            $state->addChatMessage("You: /skills see");
                            $state->addChatMessage("[System] Loaded System Skills: $sys");
                            $state->addChatMessage("[System] Loaded User Skills: $usr");
                            return false;
                        }

                        if ($keyBinding->isSkillsLoadCommand($promptText)) {
                            $args = array_values(array_filter(explode(" ", trim(substr($promptText, strlen('/skills load'))))));
                            if (count($args) < 2) {
                                $state->addChatMessage("[Error] Usage: /skills load <system|user> <file1.md>");
                                return false;
                            }
                            $type = strtolower($args[0]);
                            $files = array_slice($args, 1, 2);
                            $loadedNames = [];
                            $content = "";
                            foreach ($files as $file) {
                                if (file_exists($file)) {
                                    $content .= file_get_contents($file) . "\n\n";
                                    $loadedNames[] = $file;
                                } else {
                                    $state->addChatMessage("[Error] File not found: $file");
                                }
                            }
                            if (!empty($loadedNames)) {
                                if ($type === 'system') {
                                    $state->setLoadedSystemSkills($loadedNames);
                                    $state->setSystemSkillContext(trim($content));
                                } else {
                                    $state->setLoadedUserSkills($loadedNames);
                                    $state->setUserSkillContext(trim($content));
                                }
                                $state->addChatMessage("[System] Successfully loaded $type skills: " . implode(", ", $loadedNames));
                            }
                            return false;
                        }

                        foreach (explode("\n", $promptText) as $idx => $line) {
                            $state->addChatMessage(($idx === 0 ? "You: " : "    ") . $line);
                        }

                        $state->setProcessing(true);
                        if ($display !== null) Renderer::render($display, $state, $maxScroll);

                        $contextBlocks = [];
                        if ($state->getSystemSkillContext() !== "") $contextBlocks[] = "System Skills Context:\n" . $state->getSystemSkillContext();
                        if ($state->getUserSkillContext() !== "") $contextBlocks[] = "User Skills Context:\n" . $state->getUserSkillContext();
                        if ($state->getScannedContext() !== "") $contextBlocks[] = "Context from scanned path (" . $state->getScannedPath() . "):\n" . $state->getScannedContext();

                        $fullPrompt = empty($contextBlocks) ? $promptText : implode("\n\n", $contextBlocks) . "\n\nUser Question: " . $promptText;
                        [$cmd, $logMsg] = $llmService->resolveCommand($fullPrompt);
                        $state->addChatMessage($logMsg);
                        $logger("Executing: $cmd");

                        $output = shell_exec($cmd . " 2>&1");
                        if ($output === null || trim($output) === "") {
                            $state->addChatMessage(" > (No output returned)");
                        } else {
                            $lines = explode("\n", rtrim($output));
                            if (count($lines) > 200) $lines = array_slice($lines, -200);
                            foreach ($lines as $line) $state->addChatMessage($line);
                        }
                        $state->addChatMessage(sprintf("[System] Request completed in %s seconds.", $state->getProcessingDuration()));
                        $state->setProcessing(false);
                    }
                }
                return false;
            } elseif ($event->code === KeyCode::Up) {
                if ($state->isTerminalDrawerOpen() && $state->getInputFocus() === "terminal") {
                    $state->setTerminalScrollOffset(max(0, $state->getTerminalScrollOffset() - 1));
                } else {
                    $historyInput = $state->navigateHistory("up");
                    if ($historyInput !== null) $state->setInputText($historyInput);
                    else if ($state->getInputText() === "") { $state->setScrollOffset(max(0, $state->getScrollOffset() - 1)); $state->setAutoScroll(false); }
                }
                return false;
            } elseif ($event->code === KeyCode::Down) {
                if ($state->isTerminalDrawerOpen() && $state->getInputFocus() === "terminal") {
                    $state->setTerminalScrollOffset($state->getTerminalScrollOffset() + 1);
                } else {
                    $historyInput = $state->navigateHistory("down");
                    if ($historyInput !== null) $state->setInputText($historyInput);
                    else if ($state->getInputText() === "") { $state->setScrollOffset($state->getScrollOffset() + 1); $state->setAutoScroll(true); }
                }
                return false;
            } elseif ($event->code === KeyCode::Left) {
                if ($state->isTerminalDrawerOpen() && $state->getInputFocus() === "terminal") $state->setTerminalCursor($state->getTerminalCursor() - 1);
                else $state->setInputCursor($state->getInputCursor() - 1);
                return false;
            } elseif ($event->code === KeyCode::Right) {
                if ($state->isTerminalDrawerOpen() && $state->getInputFocus() === "terminal") $state->setTerminalCursor($state->getTerminalCursor() + 1);
                else $state->setInputCursor($state->getInputCursor() + 1);
                return false;
            } elseif ($event->code === KeyCode::Backspace) {
                if ($state->isTerminalDrawerOpen() && $state->getInputFocus() === "terminal") {
                    $state->backspaceActiveTerminalInput();
                } else {
                    $currentInput = $state->getInputText();
                    if ($keyBinding->isCommandNew($currentInput) && $state->getInputCursor() === mb_strlen($currentInput)) {
                        $state->setInputText($keyBinding->removeCommandNew($currentInput));
                    } else {
                        $state->backspaceInputText();
                    }
                }
                return false;
            }
        }

        $action = $keyBinding->matchEvent($event);
        if ($action === KeyBindingService::ACTION_EXIT || $action === KeyBindingService::ACTION_FORCE_EXIT) { $state->stop(); return true; }
        elseif ($action === KeyBindingService::ACTION_TOGGLE_FOCUS) {
            if ($state->isTerminalDrawerOpen()) { $state->toggleInputFocus(); return true; }
        } elseif ($action === KeyBindingService::ACTION_TOGGLE_TERMINAL) { $state->toggleTerminalDrawer(); return false; }
        elseif ($action === KeyBindingService::ACTION_TOGGLE_SIDEBAR) { $state->toggleSidebar(); return false; }
        elseif ($action === KeyBindingService::ACTION_PASTE) {
            $clipboardData = ClipboardService::read();
            if ($clipboardData !== "") {
                if ($state->isTerminalDrawerOpen() && $state->getInputFocus() === "terminal") $state->insertActiveTerminalInput($clipboardData);
                else $state->insertInputText($clipboardData);
            }
            return true;
        }

        if ($event instanceof CharKeyEvent) {
            if (ord($event->char) >= 32 && $event->char !== "\x7f") {
                if ($state->isTerminalDrawerOpen() && $state->getInputFocus() === "terminal") $state->insertActiveTerminalInput($event->char);
                else $state->insertInputText($event->char);
            }
        }

        return false;
    }
}
