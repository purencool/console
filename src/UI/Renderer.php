<?php
namespace App\UI;

use App\State\AppState;
use App\Support\ReflectionHelper;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Text;

/**
 *
 */
class Renderer
{
    /**
     *
     */
    public static function render(
        $display,
        AppState $state,
        int $maxScroll,
    ): void {
        [
            $dirHorizontal,
            $dirVertical,
        ] = ReflectionHelper::getDirectionClasses();
        $constraintClass = ReflectionHelper::getConstraintClass();

        $viewport = method_exists($display, "viewportArea")
            ? $display->viewportArea()
            : null;
        $termWidth = $viewport ? $viewport->width : 100;
        $termHeight = $viewport ? $viewport->height : 30;

        $paneWidthPercent = $state->isSidebarVisible() ? 70 : 100;
        $leftPaneWidth = (int) ($termWidth * ($paneWidthPercent / 100));
        $innerWidth = max(1, $leftPaneWidth - 2);

        $chatDisplayInput =
            $state->getInputText() .
            ($state->getInputFocus() === "chat" ? "█" : "");
        $chatDisplayInput = str_replace("/n", "\n", $chatDisplayInput);

        $rawLines = explode("\n", $chatDisplayInput);
        $wrappedInputLines = [];
        foreach ($rawLines as $rawLine) {
            if ($rawLine === "") {
                $wrappedInputLines[] = "";
            } else {
                foreach (
                    ReflectionHelper::wrapText($rawLine, $innerWidth)
                    as $wl
                ) {
                    $wrappedInputLines[] = $wl;
                }
            }
        }

        $inputBoxHeight = min(15, max(3, count($wrappedInputLines) + 2));

        $wrappedChatLog = [];
        foreach ($state->getChatLog() as $logLine) {
            foreach (ReflectionHelper::wrapText($logLine, $innerWidth) as $wl) {
                $wrappedChatLog[] = $wl;
            }
        }

        $terminalDrawerHeight = $state->isTerminalDrawerOpen()
            ? (int) ($termHeight * 0.4)
            : 0;
        $visibleChatHeight = max(
            5,
            $termHeight - $inputBoxHeight - $terminalDrawerHeight - 2,
        );
        $visibleLog = array_slice(
            $wrappedChatLog,
            $state->getScrollOffset(),
            $visibleChatHeight,
        );
        $chatLines = array_map(fn($msg) => Line::fromString($msg), $visibleLog);

        $chatTitle = " Chat Log [Ctrl+T: Terminal | Ctrl+S: Sidebar] ";
        if ($state->getScannedPath()) {
            $chatTitle =
                " Chat Log [Path Active: " .
                basename($state->getScannedPath()) .
                "] ";
        }

        $chatBoxTitle =
            " Input (Type path to scan or prompt, Enter to submit) ";

        $leftWidgets = [
            ReflectionHelper::createBlock($chatTitle)->widget(
                ParagraphWidget::fromText(Text::fromLines(...$chatLines)),
            ),
            ReflectionHelper::createBlock($chatBoxTitle)->widget(
                ParagraphWidget::fromString(implode("\n", $wrappedInputLines)),
            ),
        ];

        if ($state->isTerminalDrawerOpen()) {
            $visibleTermHeight = max(2, $terminalDrawerHeight - 3);
            $totalTermLines = count($state->getTerminalBuffer());
            $maxTermScroll = max(0, $totalTermLines - $visibleTermHeight);
            $termScroll = min(
                max(0, $state->getTerminalScrollOffset()),
                $maxTermScroll,
            );
            $state->setTerminalScrollOffset($termScroll);

            $visibleTermBuffer = array_slice(
                $state->getTerminalBuffer(),
                $termScroll,
                $visibleTermHeight,
            );
            $termLines = array_map(
                fn($l) => Line::fromString($l),
                $visibleTermBuffer,
            );
            $termPrompt =
                "$ " .
                $state->getActiveTerminalInput() .
                ($state->getInputFocus() === "terminal" ? "█" : "");
            $termLines[] = Line::fromString($termPrompt);

            $leftWidgets[] = ReflectionHelper::createBlock(
                " Terminal Drawer (Tab to focus) ",
            )->widget(
                ParagraphWidget::fromText(Text::fromLines(...$termLines)),
            );
        }

        $constraints = [$constraintClass::min(0)];
        $constraints[] = $constraintClass::length($inputBoxHeight);
        if ($state->isTerminalDrawerOpen()) {
            $constraints[] = $constraintClass::length($terminalDrawerHeight);
        }

        $leftGrid = GridWidget::default()
            ->direction($dirVertical)
            ->constraints(...$constraints)
            ->widgets(...$leftWidgets);

        if ($state->isSidebarVisible()) {
            $controlLines = [
                Line::fromString("Active Scanned Path:"),
                Line::fromString($state->getScannedPath() ?? "None"),
                Line::fromString(""),
                Line::fromString("[ SUBMIT ARTISAN COMMAND ]"),
            ];

            $display->draw(
                GridWidget::default()
                    ->direction($dirHorizontal)
                    ->constraints(
                        $constraintClass::percentage(70),
                        $constraintClass::percentage(30),
                    )
                    ->widgets(
                        $leftGrid,
                        ReflectionHelper::createBlock(
                            " Controls [Ctrl+S to hide] ",
                        )->widget(
                            ParagraphWidget::fromText(
                                Text::fromLines(...$controlLines),
                            ),
                        ),
                    ),
            );
        } else {
            $display->draw(
                GridWidget::default()
                    ->direction($dirHorizontal)
                    ->constraints($constraintClass::percentage(100))
                    ->widgets($leftGrid),
            );
        }
    }
}
