<?php
namespace App\Services;

class TerminalLayoutService
{
    private string $spanClass;
    private string $styleClass;
    private string $colorClass;

    private int $innerWidth = 100;
    private int $sliceChatHeight = 20;
    private array $prefixes = [];
    private mixed $bgStyle = null;

    public function __construct()
    {
        $this->spanClass = class_exists("\PhpTui\Tui\Text\Span")
            ? "\PhpTui\Tui\Text\Span"
            : "\PhpTui\Tui\Model\Widget\Span";
        $this->styleClass = class_exists("\PhpTui\Tui\Style\Style")
            ? "\PhpTui\Tui\Style\Style"
            : "\PhpTui\Tui\Model\Style";
        $this->colorClass = class_exists("\PhpTui\Tui\Color\AnsiColor")
            ? "\PhpTui\Tui\Color\AnsiColor"
            : "\PhpTui\Tui\Model\Color\AnsiColor";

        $style = $this->styleClass;
        $color = $this->colorClass;

        $this->bgStyle = $style::default()->bg($color::DarkGray);
        $this->prefixes = [
            "[System]" => $style::default()->fg($color::Yellow),
            "[Agent]" => $style::default()->fg($color::Green),
            "[Error]" => $style::default()->fg($color::Red),
            "You:" => $style::default()->fg($color::Cyan),
        ];
    }

    public function getSpanClass(): string
    {
        return $this->spanClass;
    }
    public function getPrefixes(): array
    {
        return $this->prefixes;
    }
    public function getBgStyle(): mixed
    {
        return $this->bgStyle;
    }
    public function getInnerWidth(): int
    {
        return $this->innerWidth;
    }
    public function getSliceChatHeight(): int
    {
        return $this->sliceChatHeight;
    }

    public function getDimensions(object $display): array
    {
        $viewport = method_exists($display, "viewportArea")
            ? $display->viewportArea()
            : null;
        return [
            "width" => $viewport ? $viewport->width : 100,
            "height" => $viewport ? $viewport->height : 30,
        ];
    }

    public function shouldShowCursor(): bool
    {
        return (int) (microtime(true) * 2) % 2 === 0;
    }

    public function getLayoutMetrics(int $hPad = 1, int $vPad = 1): array
    {
        return [
            "hPad" => $hPad,
            "vPad" => $vPad,
            "hPadStr" => str_repeat(" ", $hPad),
        ];
    }

    public function calculateGridGeometry(
        int $termWidth,
        bool $isSidebarVisible,
        int $hPad,
    ): array {
        $paneWidthPercent = $isSidebarVisible ? 70 : 100;
        $leftPaneWidth = (int) ($termWidth * ($paneWidthPercent / 100));
        $this->innerWidth = max(10, $leftPaneWidth - 2 - $hPad * 2);

        return [
            "leftPaneWidth" => $leftPaneWidth,
            "innerWidth" => $this->innerWidth,
        ];
    }

    /**
     * Computes and saves vertical pane split segments state parameters into centralized memory.
     */
    public function calculatePaneHeights(
        int $termHeight,
        int $inputBoxHeight,
        bool $isTerminalOpen,
        int $vPad,
    ): array {
        // Calculate the rows allocated to the terminal drawer (40% of screen)
        $drawerHeight = $isTerminalOpen ? (int) ($termHeight * 0.4) : 0;

        // Subtract structural borders (4) + padding loops ($vPad * 2)
        $decorationOverhead = 4 + $vPad * 2 - 2;

        // Isolate the exact rows left over for the chat log area text rows
        $visibleChatHeight =
            $termHeight - $inputBoxHeight - $drawerHeight - $decorationOverhead;

        // Save the exact printable text row split tightly to memory
        $this->sliceChatHeight = max(1, $visibleChatHeight);

        return [
            "drawerHeight" => $drawerHeight,
            "sliceChatHeight" => $this->sliceChatHeight,
        ];
    }

    /**
     * Calculates the exact number of wrapped TUI lines a message block will consume.
     *
     * @param string|array $message Dynamic log line text or an array of lines
     */
    public function calculateMessageLinesSize(mixed $message): int
    {
        $lines = is_array($message)
            ? $message
            : explode("\n", (string) $message);
        $totalLinesCount = 0;

        foreach ($lines as $line) {
            // Count the lines after running them through your exact word wrap rules
            $wrappedSegments = \App\Support\ReflectionHelper::wrapText(
                $line,
                $this->innerWidth,
            );
            $totalLinesCount += max(1, count($wrappedSegments));
        }

        return $totalLinesCount;
    }

    /**
     *
     * @return array{chatTitle: string, inputTitle: string}
     */
    public function compileWidgetTitles(object $state): array
    {
        $scannedPath = method_exists($state, "getScannedPath")
            ? $state->getScannedPath()
            : null;
        $isProcessing = method_exists($state, "isProcessing")
            ? $state->isProcessing()
            : false;

        return [
            "chatTitle" => $scannedPath
                ? " Chat Log [Path Active: " . basename($scannedPath) . "] "
                : " Chat Log [Ctrl+T: Terminal | Ctrl+S: Sidebar] ",
            "inputTitle" => $isProcessing
                ? " Input [Processing Request...] "
                : " Input (Type / for help, Enter to submit) ",
        ];
    }
}
