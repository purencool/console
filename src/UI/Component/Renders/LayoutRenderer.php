<?php
namespace App\UI\Component\Renders;

use App\State\AppState;
use App\Support\ReflectionHelper;
use App\Services\TerminalLayoutService;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Text;

class LayoutRenderer
{
    /**
     * Compiles grid coordinates and balances layout splits between panels.
     *
     * @param array<string, object> $services Centralised system dependency registry
     * @param array<object> $leftWidgets Aggregated panel widget matrix stack structures
     */
    public function render(
        object $display,
        array $services,
        array $leftWidgets,
        int $termHeight,
        int $inputBoxHeight,
        int $terminalDrawerHeight,
    ): void {
        /** @var AppState $state */
        $state = $services["state"];

        /** @var TerminalLayoutService $layoutService */
        $layoutService = $services["layout"];

        // Dynamically read padding and design tokens from the layout service
        $metrics = $layoutService->getLayoutMetrics(hPad: 1, vPad: 1);
        $vPad = $metrics["vPad"] ?? 1;
        $hPadStr = $metrics["hPadStr"] ?? " ";

        // Resolve Dynamic Reflection Framework Adapters
        [
            $dirHorizontal,
            $dirVertical,
        ] = ReflectionHelper::getDirectionClasses();
        $constraintClass = ReflectionHelper::getConstraintClass();

        // Compute Left Side Interface Geometry Layout Stack
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

        // Compile Collapsible Sidebar Viewport Grid Section
        if ($state->isSidebarVisible()) {
            $sidebarLines = [];
            for ($i = 0; $i < $vPad; $i++) {
                $sidebarLines[] = Line::fromString("");
            }

            $sidebarLines[] = Line::fromString($hPadStr . "📁 Active Files");

            // Extract agent updates cleanly to generate quick contextual indicators
            foreach ($state->getChatLog() as $logLine) {
                if (str_contains($logLine, "[Agent]")) {
                    $sidebarLines[] = Line::fromString(
                        $hPadStr . "  • " . substr(basename($logLine), 0, 15),
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

        // Project full panel grid matrix straight to screen buffer channels
        $display->draw($mainGrid);
    }
}
