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
     * Renders the interactive fullscreen text editor pane layout.
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

        // Dynamically read hardware dimension parameters straight out of your layout service!
        $dims       = $layoutService->getDimensions($display);
        $termHeight = $dims['height'];

        // Pull dynamic style tokens and metrics from your centralised layout service
        $metrics = $layoutService->getLayoutMetrics(hPad: 1, vPad: 1);
        $vPad    = $metrics['vPad'];
        $hPadStr = $metrics['hPadStr'];

        $constraintClass = ReflectionHelper::getConstraintClass();

        // Core Layout Styles
        $bgStyle      = $this->styleClass::default()->bg($this->colorClass::DarkGray);
        $lineNumStyle = $this->styleClass::default()->fg($this->colorClass::DarkGray);

        $lines = $state->getEditorLines();
        $row   = $state->getEditorRow();
        $col   = $state->getEditorCol();

        // 2. Compute Vertical Viewport Scroll Adjustments
        $visibleLines = max(1, $termHeight - 2 - ($vPad * 2));
        $scrollY      = $state->getEditorScrollY();

        if ($row < $scrollY) {
            $scrollY = $row;
        } elseif ($row >= $scrollY + $visibleLines) {
            $scrollY = $row - $visibleLines + 1;
        }
        $state->setEditorScrollY($scrollY);

        // Slice current window region viewport out of text file layout buffers
        $renderLines = array_slice($lines, $scrollY, $visibleLines);
        $tuiLines    = [];

        for ($i = 0; $i < $vPad; $i++) {
            $tuiLines[] = Line::fromString("");
        }

        // Calculate dynamic line number gutter width alignment boundaries
        $totalLines   = count($lines);
        $lineNumWidth = max(3, strlen((string)$totalLines));

        // 3. Build Render Lines and Process Cursor Highlights
        foreach ($renderLines as $idx => $lineContent) {
            $actualLineNum = $scrollY + $idx + 1;
            $lineNumStr    = str_pad((string)$actualLineNum, $lineNumWidth, " ", STR_PAD_LEFT) . " │ ";

            $spans = [
                $this->spanClass::fromString($hPadStr),
                $this->spanClass::fromString($lineNumStr)->style($lineNumStyle),
            ];

            // If the row matches the cursor row position, paint the active split text spans
            if ($idx === ($row - $scrollY)) {
                $char  = ($col >= mb_strlen($lineContent)) ? " " : mb_substr($lineContent, $col, 1);
                $left  = mb_substr($lineContent, 0, $col);
                $right = mb_substr($lineContent, $col + 1);

                if ($left !== "") {
                    $spans[] = $this->spanClass::fromString($left);
                }

                $spans[] = $showCursor
                    ? $this->spanClass::fromString($char)->style($bgStyle)
                    : $this->spanClass::fromString($char);

                if ($right !== "") {
                    $spans[] = $this->spanClass::fromString($right);
                }
            } else {
                $spans[] = $this->spanClass::fromString($lineContent);
            }

            $tuiLines[] = Line::fromSpans(...$spans);
        }

        for ($i = 0; $i < $vPad; $i++) {
            $tuiLines[] = Line::fromString("");
        }

        // 4. Compile Title Header Metadata and Draw Workspace
        $statusText = $state->getEditorStatus() ?: "Ready";
        $title = sprintf(
            " Editor: %s [Row: %d, Col: %d] [Ctrl+S: Save | Esc: Close] - %s ",
            $state->getEditorFilePath(),
            $row + 1,
            $col + 1,
            $statusText
        );

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
