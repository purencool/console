<?php
namespace App\UI\Component\Renders;

use App\State\AppState;
use App\Support\ReflectionHelper;
use App\Services\TerminalLayoutService;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Text;

class EditorRenderer
{
    private string $spanClass;
    private string $styleClass;
    private string $colorClass;

    public function __construct()
    {
        // Cache class resolutions once to prevent reflection overhead during hot draw ticks
        $this->spanClass  = class_exists('\PhpTui\Tui\Text\Span') ? '\PhpTui\Tui\Text\Span' : '\PhpTui\Tui\Model\Widget\Span';
        $this->styleClass = class_exists('\PhpTui\Tui\Style\Style') ? '\PhpTui\Tui\Style\Style' : '\PhpTui\Tui\Model\Style';
        $this->colorClass = class_exists('\PhpTui\Tui\Color\AnsiColor') ? '\PhpTui\Tui\Color\AnsiColor' : '\PhpTui\Tui\Model\Color\AnsiColor';
    }

    /**
     * Renders the interactive fullscreen text editor pane layout with automatic word-wrapping.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function render(
        object $display,
        array $services,
        bool $showCursor
    ): void {
        /** @var AppState $state */
        $state = $services["state"];

        /** @var TerminalLayoutService $layoutService */
        $layoutService = $services["layout"];

        // Grab the explicit, dedicated EditorState instance directly to match your handlers
        $editor = method_exists($state, 'editor') ? $state->editor() : $state;

        // Dynamically read hardware dimension parameters straight out of your layout service
        $dims       = $layoutService->getDimensions($display);
        $termHeight = $dims['height'];
        $termWidth  = $dims['width'];

        // Pull dynamic style tokens and metrics from your centralised layout service
        $metrics = $layoutService->getLayoutMetrics(hPad: 1, vPad: 1);
        $vPad    = $metrics['vPad'];
        $hPadStr = $metrics['hPadStr'];

        $constraintClass = ReflectionHelper::getConstraintClass();

        // Core Layout Styles
        $bgStyle        = $this->styleClass::default()->bg($this->colorClass::DarkGray);
        $lineNumStyle   = $this->styleClass::default()->fg($this->colorClass::DarkGray);
        $aiPromptStyle  = $this->styleClass::default()->fg($this->colorClass::Cyan)->bg($this->colorClass::Black);
        $aiTipStyle     = $this->styleClass::default()->fg($this->colorClass::DarkGray);
        $selectionStyle = $this->styleClass::default()->bg($this->colorClass::Blue)->fg($this->colorClass::White);

        $lines = $state->getEditorLines();
        $row   = $state->getEditorRow();
        $col   = $state->getEditorCol();

        // Calculate workspace width limits left over for text content after subtracting gutter column spaces
        $totalLines   = count($lines);
        $lineNumWidth = max(3, strlen((string)$totalLines));
        $gutterWidth  = strlen($hPadStr) + $lineNumWidth + 3; // Length of hPad + number + " │ "
        $textCanvasAvailableWidth = max(10, $termWidth - $gutterWidth - 2);

        // 💡 1. DYNAMIC VIRTUAL WRAPPING PIPELINE MATRIX MAP
        $virtualScreenLines = [];
        $cursorVirtualRowIndex = 0;

        foreach ($lines as $realLineIdx => $lineContent) {
            if ($lineContent === "") {
                $virtualScreenLines[] = [
                    'real_row'   => $realLineIdx,
                    'is_subline' => false,
                    'text'       => "",
                    'start_col'  => 0
                ];
                if ($realLineIdx === $row) {
                    $cursorVirtualRowIndex = count($virtualScreenLines) - 1;
                }
                continue;
            }

            $segments = ReflectionHelper::wrapText($lineContent, $textCanvasAvailableWidth);
            $accumulatedCharOffset = 0;

            foreach ($segments as $subIdx => $segmentText) {
                $segmentLength = mb_strlen($segmentText);

                $virtualScreenLines[] = [
                    'real_row'   => $realLineIdx,
                    'is_subline' => ($subIdx > 0),
                    'text'       => $segmentText,
                    'start_col'  => $accumulatedCharOffset
                ];

                if ($realLineIdx === $row && $col >= $accumulatedCharOffset && $col <= ($accumulatedCharOffset + $segmentLength)) {
                    if ($col < ($accumulatedCharOffset + $segmentLength) || $subIdx === (count($segments) - 1)) {
                        $cursorVirtualRowIndex = count($virtualScreenLines) - 1;
                    }
                }

                $accumulatedCharOffset += $segmentLength;
            }
        }

        // 💡 2. VERTICAL VIEWPORT SCROLL ADJUSTMENTS AGAINST VIRTUAL LINES
        $visibleLines = max(1, $termHeight - 3 - ($vPad * 2));
        $scrollY      = $state->getEditorScrollY();

        if ($cursorVirtualRowIndex < $scrollY) {
            $scrollY = $cursorVirtualRowIndex;
        } elseif ($cursorVirtualRowIndex >= $scrollY + $visibleLines) {
            $scrollY = $cursorVirtualRowIndex - $visibleLines + 1;
        }
        $state->setEditorScrollY($scrollY);

        // Slice virtual view segments out of our wrapped tracking rows array map
        $renderSegments = array_slice($virtualScreenLines, $scrollY, $visibleLines);
        $tuiLines       = [];

        for ($i = 0; $i < $vPad; $i++) {
            $tuiLines[] = Line::fromString("");
        }

        foreach ($renderSegments as $idx => $segmentMeta) {
            $currentVirtualRowIdx = $scrollY + $idx;

            if (!$segmentMeta['is_subline']) {
                $displayLineNum = $segmentMeta['real_row'] + 1;
                $lineNumStr = str_pad((string)$displayLineNum, $lineNumWidth, " ", STR_PAD_LEFT) . " │ ";
            } else {
                $lineNumStr = str_repeat(" ", $lineNumWidth) . " ┆ ";
            }

            $spans = [
                $this->spanClass::fromString($hPadStr),
                $this->spanClass::fromString($lineNumStr)->style($lineNumStyle),
            ];

            // DELEGATED CONTEXT PASSTHROUGH ABSTRACTED CALL
            \App\UI\Component\Editor\EditorSpanCollectionRenderer::buildSpans(
                $spans,
                $segmentMeta,
                $editor,
                $currentVirtualRowIdx,
                $cursorVirtualRowIndex,
                $row,
                $col,
                $showCursor,
                $this->spanClass,
                $bgStyle,
                $selectionStyle
            );

            $tuiLines[] = Line::fromSpans(...$spans);
        }

        for ($i = 0; $i < $vPad; $i++) {
            $tuiLines[] = Line::fromString("");
        }

        $aiPromptOpen = method_exists($editor, 'isAiPromptOpen') ? $editor->isAiPromptOpen() : false;
        $selectActive = method_exists($editor, 'isSelectModeActive') ? $editor->isSelectModeActive() : false;
        $aiPromptText = method_exists($editor, 'getAiPromptText') ? $editor->getAiPromptText() : "";

        if ($aiPromptOpen) {
            $aiSpan = $this->spanClass::fromString(" ➔ [AI Agent Refactor]: " . $aiPromptText . "█")->style($aiPromptStyle);
            $tuiLines[] = Line::fromSpans($aiSpan);
        } elseif ($selectActive) {
            $selectSpan = $this->spanClass::fromString(" 🟦 [Selection Mode]: Active! Use Arrows to expand highlight, [Backspace] to delete block.")->style($this->styleClass::default()->fg($this->colorClass::Yellow)->bg($this->colorClass::Black));
            $tuiLines[] = Line::fromSpans($selectSpan);
        } else {
            $tipSpan = $this->spanClass::fromString(" 💡 Hints: [Tab] Switch Panels | [Ctrl+A] AI Refactor | [Ctrl+B] Block Select | [Ctrl+S] Save")->style($aiTipStyle);
            $tuiLines[] = Line::fromSpans($tipSpan);
        }

        for ($i = 0; $i < $vPad; $i++) {
            $tuiLines[] = Line::fromString("");
        }

        // =========================================================================
        // 💡 FIXED: Compile Title Header Metadata Globals Safely Outside Blocks!
        // =========================================================================
        $statusText = $state->getEditorStatus() ?: "Ready";
        $title = sprintf(
            " Editor: %s [Row: %d, Col: %d] [Ctrl+S: Save | Esc: Close] - %s ",
            $state->getEditorFilePath() ?: "No File Open",
            $row + 1,
            $col + 1,
            $statusText
        );

        $sidebarVisible = method_exists($editor, 'isCtrlKeyPressed') ? $editor->isCtrlKeyPressed() : false;
        if ($sidebarVisible) {
            $items = method_exists($editor, 'getSidebarItems') ? $editor->getSidebarItems() : [];
            if (empty($items) && method_exists($editor, 'refreshSidebarTree')) {
                $editor->refreshSidebarTree();
                $items = $editor->getSidebarItems();
            }

            $sidebarLines = [];
            $focusedRow   = method_exists($editor, 'getSidebarFocusRow') ? $editor->getSidebarFocusRow() : 0;
            $focusStyle   = $this->styleClass::default()->bg($this->colorClass::Blue)->fg($this->colorClass::White);

            // Print header metadata line inside the file viewer canvas itself
            $sidebarLines[] = Line::fromString(" 💡 Navigation Hints: Use [Up/Down] Arrows | Release [Ctrl] to return to code");
            $sidebarLines[] = Line::fromString(" -------------------------------------------------------------------------");

            foreach ($items as $idx => $item) {
                $icon = $item['is_dir'] ? "📁 " : "📄 ";
                if ($idx === $focusedRow) {
                    $sidebarLines[] = Line::fromSpans($this->spanClass::fromString(" " . $icon . $item['name'])->style($focusStyle));
                } else {
                    $sidebarLines[] = Line::fromString(" " . $icon . $item['name']);
                }
            }

            $display->draw(
                GridWidget::default()
                    ->constraints($constraintClass::percentage(100))
                    ->widgets(
                        ReflectionHelper::createBlock(" Project Navigator Workspace [FULL REWRITE PANEL] ")->widget(
                            ParagraphWidget::fromText(Text::fromLines(...$sidebarLines))
                        )
                    )
            );
        } else {
            // Takes up 100% of the screen limits when Ctrl is not held down
            $display->draw(
                GridWidget::default()
                    ->constraints($constraintClass::percentage(100))
                    ->widgets(
                        ReflectionHelper::createBlock($title)->widget(
                            ParagraphWidget::fromText(Text::fromLines(...$tuiLines))
                        )
                    )
            );
        }
    }
}
