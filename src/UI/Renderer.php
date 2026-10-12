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
    // Cached renderer instances to prevent allocation churn on every frame
    private ?EditorRenderer $editorRenderer = null;
    private ?InputBoxRenderer $inputBoxRenderer = null;
    private ?ChatRenderer $chatRenderer = null;
    private ?TerminalRenderer $terminalRenderer = null;
    private ?LayoutRenderer $layoutRenderer = null;

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
            $this->editorRenderer ??= new EditorRenderer();
            $this->editorRenderer->render($display, $services, $showCursor);
            return;
        }

        // Perform Grid Matrix Boundary Calculations
        $geometry = $layoutService->calculateGridGeometry(
            $dims["width"],
            $state->isSidebarVisible(),
            $metrics["hPad"],
        );

        $this->inputBoxRenderer ??= new InputBoxRenderer();
        $inputData = $this->inputBoxRenderer->render(
            $services,
            $geometry["innerWidth"],
            $layoutService->getSpanClass(),
            $layoutService->getBgStyle(),
        );

        // Restore the dynamic height split calculations calculation right here!
        $layoutService->calculatePaneHeights(
            $dims["height"],
            $inputData["height"],
            $state->isTerminalDrawerOpen(),
            $metrics["vPad"],
        );

        // Dynamic Title Retrieval via Layout Service
        $titles = $layoutService->compileWidgetTitles($state);

        // Assemble Widgets Stack
        $this->chatRenderer ??= new ChatRenderer();
        $leftWidgets = [
            ReflectionHelper::createBlock($titles["chatTitle"])->widget(
                $this->chatRenderer->render($services),
            ),
            ReflectionHelper::createBlock($titles["inputTitle"])->widget(
                ParagraphWidget::fromText(
                    Text::fromLines(...$inputData["lines"]),
                ),
            ),
        ];

        if ($state->isTerminalDrawerOpen()) {
            $this->terminalRenderer ??= new TerminalRenderer();
            $termLines = $this->terminalRenderer->render(
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
        $this->layoutRenderer ??= new LayoutRenderer();
        $this->layoutRenderer->render(
            $display,
            $services,
            $leftWidgets,
            $inputData,
        );
    }
}
