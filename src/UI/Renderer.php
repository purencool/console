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
    private string $spanClass;

    public function __construct()
    {
        // Cache class resolution once to prevent reflection overhead during hot draw ticks
        $this->spanClass = class_exists("\PhpTui\Tui\Text\Span")
            ? "\PhpTui\Tui\Text\Span"
            : "\PhpTui\Tui\Model\Widget\Span";
    }

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

        // Ingest View Dimensions and Layout Metrics from Service
        $dims = $layoutService->getDimensions($display);
        $showCursor = $layoutService->shouldShowCursor();
        $metrics = $layoutService->getLayoutMetrics(hPad: 1, vPad: 1);

        $termWidth = $dims["width"];
        $termHeight = $dims["height"];
        $vPad = $metrics["vPad"];
        $hPad = $metrics["hPad"];
        $hPadStr = $metrics["hPadStr"];

        if ($state->isEditorOpen()) {
            new EditorRenderer()->render($display, $services, $showCursor);
            return;
        }

        // Delegate Grid Geometry Calculations
        $geometry = $layoutService->calculateGridGeometry(
            $termWidth,
            $state->isSidebarVisible(),
            $hPad,
        );
        $innerWidth = $geometry["innerWidth"];

        // Render Input Panel Area
        $inputData = new InputBoxRenderer()->render(
            $services,
            $innerWidth,
            $this->spanClass,
            $metrics["bgStyle"],
        );
        $inputBoxHeight = $inputData["height"];

        $heights = $layoutService->calculatePaneHeights(
            $termHeight,
            $inputBoxHeight,
            $state->isTerminalDrawerOpen(),
            $vPad,
        );
        $terminalDrawerHeight = $heights["drawerHeight"];
        $sliceChatHeight = $heights["sliceChatHeight"];

        // 4. Render Layout Sub-Components
        $chatWidget = new ChatRenderer()->render(
            $services,
            $sliceChatHeight,
            $this->spanClass,
            $metrics["prefixes"],
        );

        $chatTitle = $state->getScannedPath()
            ? " Chat Log [Path Active: " .
                basename($state->getScannedPath()) .
                "] "
            : " Chat Log [Ctrl+T: Terminal | Ctrl+S: Sidebar] ";
        $chatBoxTitle = $state->isProcessing()
            ? " Input [Processing Request...] "
            : " Input (Type / for help, Enter to submit) ";

        $leftWidgets = [
            ReflectionHelper::createBlock($chatTitle)->widget($chatWidget),
            ReflectionHelper::createBlock($chatBoxTitle)->widget(
                ParagraphWidget::fromText(
                    Text::fromLines(...$inputData["lines"]),
                ),
            ),
        ];

        if ($state->isTerminalDrawerOpen()) {
            $termLines = new TerminalRenderer()->render(
                $services,
                $this->spanClass,
                $metrics["bgStyle"],
                $showCursor,
            );
            $leftWidgets[] = ReflectionHelper::createBlock(
                " Terminal ",
            )->widget(
                ParagraphWidget::fromText(Text::fromLines(...$termLines)),
            );
        }

        // Delegate Final UI Frame Compilation
        new LayoutRenderer()->render(
            $display,
            $services,
            $leftWidgets,
            $termHeight,
            $inputBoxHeight,
            $terminalDrawerHeight,
        );
    }
}
