<?php
namespace App\UI\Component\Renders;

use App\State\AppState;
use App\Support\ReflectionHelper;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Text;

class LayoutRenderer
{
    /**
     * Handles compiling Left Widgets and managing Sidebar grid constraints.
     */
    public function render(
        $display,
        AppState $state,
        array $leftWidgets,
        int $termWidth,
        int $termHeight,
        int $inputBoxHeight,
        int $terminalDrawerHeight,
        int $vPad,
        string $hPadStr,
        string $spanClass,
    ): void {
        $dirHorizontal = ReflectionHelper::getDirectionClasses()[0];
        $dirVertical = ReflectionHelper::getDirectionClasses()[1];
        $constraintClass = ReflectionHelper::getConstraintClass();

        // Calculate heights dynamically for the left layout stack
        $remainingHeight = max(
            0,
            $termHeight - $inputBoxHeight - $terminalDrawerHeight,
        );

        $leftConstraints = [
            $constraintClass::length($remainingHeight),
            $constraintClass::length($inputBoxHeight),
        ];

        if ($state->isTerminalDrawerOpen()) {
            $leftConstraints[] = $constraintClass::length(
                $terminalDrawerHeight,
            );
        }

        $leftGrid = GridWidget::default()
            ->direction($dirVertical)
            ->constraints(...$leftConstraints)
            ->widgets(...$leftWidgets);

        // Sidebar Grid Compilation
        if ($state->isSidebarVisible()) {
            $sidebarLines = [];
            for ($i = 0; $i < $vPad; $i++) {
                $sidebarLines[] = Line::fromString("");
            }

            // Dummy content representing sidebar data tracking
            $sidebarLines[] = Line::fromString($hPadStr . "📁 Active Files");
            foreach ($state->getChatLog() as $log) {
                if (str_contains($log, "[Agent]")) {
                    $sidebarLines[] = Line::fromString(
                        $hPadStr . "  • " . substr(basename($log), 0, 15),
                    );
                }
            }

            $sidebarWidget = ReflectionHelper::createBlock(
                " Sidebar [Context] ",
            )->widget(
                ParagraphWidget::fromText(Text::fromLines(...$sidebarLines)),
            );

            $mainGrid = GridWidget::default()
                ->direction($dirHorizontal)
                ->constraints(
                    $constraintClass::percentage(70),
                    $constraintClass::percentage(30),
                )
                ->widgets($leftGrid, $sidebarWidget);
        } else {
            $mainGrid = $leftGrid;
        }

        $display->draw($mainGrid);
    }
}
