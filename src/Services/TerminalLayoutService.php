<?php
namespace App\Services;

class TerminalLayoutService
{
    private string $styleClass;
    private string $colorClass;

    public function __construct()
    {
        $this->styleClass = class_exists("\PhpTui\Tui\Style\Style")
            ? "\PhpTui\Tui\Style\Style"
            : "\PhpTui\Tui\Model\Style";
        $this->colorClass = class_exists("\PhpTui\Tui\Color\AnsiColor")
            ? "\PhpTui\Tui\Color\AnsiColor"
            : "\PhpTui\Tui\Model\Color\AnsiColor";
    }

    /**
     * Resolves terminal view dimensions safely.
     *
     * @return array{width: int, height: int}
     */
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

    /**
     * Calculates the cursor frequency blink toggle.
     */
    public function shouldShowCursor(): bool
    {
        return (int) (microtime(true) * 2) % 2 === 0;
    }

    /**
     * Generates a structural configuration profile layout design token map.
     */
    public function getLayoutMetrics(int $hPad = 1, int $vPad = 1): array
    {
        $color = $this->colorClass;
        $style = $this->styleClass;

        return [
            "hPad" => $hPad,
            "vPad" => $vPad,
            "hPadStr" => str_repeat(" ", $hPad),
            "bgStyle" => $style::default()->bg($color::DarkGray),
            "prefixes" => [
                "[System]" => $style::default()->fg($color::Yellow),
                "[Agent]" => $style::default()->fg($color::Green),
                "[Error]" => $style::default()->fg($color::Red),
                "You:" => $style::default()->fg($color::Cyan),
            ],
        ];
    }

    /**
     * Computes geometry grid widths dynamically based on sidebar state constraints.
     *
     * @return array{leftPaneWidth: int, innerWidth: int}
     */
    public function calculateGridGeometry(
        int $termWidth,
        bool $isSidebarVisible,
        int $hPad,
    ): array {
        $paneWidthPercent = $isSidebarVisible ? 70 : 100;
        $leftPaneWidth = (int) ($termWidth * ($paneWidthPercent / 100));
        $innerWidth = max(1, $leftPaneWidth - 2 - $hPad * 2);

        return [
            "leftPaneWidth" => $leftPaneWidth,
            "innerWidth" => $innerWidth,
        ];
    }

    /**
     * Calculates the exact heights for the layout sections based on container rules.
     *
     * @return array{drawerHeight: int, sliceChatHeight: int}
     */
    public function calculatePaneHeights(
        int $termHeight,
        int $inputBoxHeight,
        bool $isTerminalOpen,
        int $vPad,
    ): array {
        // Calculate the height of the terminal panel (40% of view)
        $drawerHeight = $isTerminalOpen ? (int) ($termHeight * 0.4) : 0;

        // Compute the remaining lines left over for rendering chat rows
        $visibleChatHeight = max(
            5,
            $termHeight - $inputBoxHeight - $drawerHeight - 2,
        );
        $sliceChatHeight = max(1, $visibleChatHeight - $vPad * 2);

        return [
            "drawerHeight" => $drawerHeight,
            "sliceChatHeight" => $sliceChatHeight,
        ];
    }
}
