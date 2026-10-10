<?php
namespace App\UI;

use App\State\AppState;
use App\Services\TerminalLayoutService;
use App\Support\ReflectionHelper;
use App\UI\Component\Renders\EditorRenderer;
use App\UI\Component\Renders\ChatRenderer;
use App\UI\Component\Renders\InputBoxRenderer;
use App\UI\Component\Renders\TerminalRenderer;
use App\UI\Component\Renders\LayoutRenderer;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Text\Text;

class Renderer
{
    /**
     * Master UI Drawing Gateway orchestrating sub-pane composition.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function render(object $display, array $services): void
    {
        /** @var AppState $state */
        $state = $services["state"];
        /** @var TerminalLayoutService $layoutService */
        $layoutService = $services["layout"];

        // Gather Terminal Parameters & Presentation Metrics
        $dims = $layoutService->getDimensions($display);
        $showCursor = $layoutService->shouldShowCursor();
        $metrics = $layoutService->getLayoutMetrics(hPad: 1, vPad: 1);

        if ($state->isEditorOpen()) {
            new EditorRenderer()->render($display, $services, $showCursor);
            return;
        }

        // Perform Grid Matrix Boundary Calculations
        $geometry = $layoutService->calculateGridGeometry(
            $dims["width"],
            $state->isSidebarVisible(),
            $metrics["hPad"],
        );

        $inputData = new InputBoxRenderer()->render(
            $services,
            $geometry["innerWidth"],
            $layoutService->getSpanClass(),
            $layoutService->getBgStyle(),
        );

        // 💡 FIXED: Restore the dynamic height split calculations calculation right here!
        // This calculates inner parameters and updates the Layout Service prior to rendering the Chat block.
        $layoutService->calculatePaneHeights(
            $dims["height"],
            $inputData["height"],
            $state->isTerminalDrawerOpen(),
            $metrics["vPad"],
        );

        // Dynamic Title Retrieval via Layout Service
        $titles = $layoutService->compileWidgetTitles($state);

        // Assemble Widgets Stack
        $leftWidgets = [
            ReflectionHelper::createBlock($titles["chatTitle"])->widget(
                new ChatRenderer()->render($services),
            ),
            ReflectionHelper::createBlock($titles["inputTitle"])->widget(
                ParagraphWidget::fromText(
                    Text::fromLines(...$inputData["lines"]),
                ),
            ),
        ];

        if ($state->isTerminalDrawerOpen()) {
            $termLines = new TerminalRenderer()->render(
                $services,
                $layoutService->getSpanClass(),
                $layoutService->getBgStyle(),
                $showCursor,
            );
            $leftWidgets[] = ReflectionHelper::createBlock(
                " Terminal ",
            )->widget(
                ParagraphWidget::fromText(Text::fromLines(...$termLines)),
            );
        }

        // Final Canvas Projection
        new LayoutRenderer()->render(
            $display,
            $services,
            $leftWidgets,
            $inputData,
        );
    }
}
