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

    public function calculatePaneHeights(
        int $termHeight,
        int $inputBoxHeight,
        bool $isTerminalOpen,
        int $vPad,
    ): array {
        $drawerHeight = $isTerminalOpen ? (int) ($termHeight * 0.4) : 0;
        $visibleChatHeight = max(
            5,
            $termHeight - $inputBoxHeight - $drawerHeight - 2,
        );
        $this->sliceChatHeight = max(1, $visibleChatHeight - $vPad * 2);

        return [
            "drawerHeight" => $drawerHeight,
            "sliceChatHeight" => $this->sliceChatHeight,
        ];
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
