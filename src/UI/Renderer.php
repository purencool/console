<?php
namespace App\UI;

use App\State\AppState;
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
    public static function render(
        $display,
        AppState $state,
        int $maxScroll,
    ): void {
        $viewport = method_exists($display, "viewportArea")
            ? $display->viewportArea()
            : null;
        $termWidth = $viewport ? $viewport->width : 100;
        $termHeight = $viewport ? $viewport->height : 30;

        $showCursor = (int) (microtime(true) * 2) % 2 === 0;

        // Shared padding configurations
        $hPad = 1;
        $vPad = 1;
        $hPadStr = str_repeat(" ", $hPad);

        $spanClass = class_exists("\PhpTui\Tui\Text\Span")
            ? "\PhpTui\Tui\Text\Span"
            : "\PhpTui\Tui\Model\Widget\Span";
        $styleClass = class_exists("\PhpTui\Tui\Style\Style")
            ? "\PhpTui\Tui\Style\Style"
            : "\PhpTui\Tui\Model\Style";
        $colorClass = class_exists("\PhpTui\Tui\Color\AnsiColor")
            ? "\PhpTui\Tui\Color\AnsiColor"
            : "\PhpTui\Tui\Model\Color\AnsiColor";

        $bgStyle = $styleClass::default()->bg($colorClass::DarkGray);

        $prefixes = [
            "[System]" => $styleClass::default()->fg($colorClass::Yellow),
            "[Agent]" => $styleClass::default()->fg($colorClass::Green),
            "[Error]" => $styleClass::default()->fg($colorClass::Red),
            "You:" => $styleClass::default()->fg($colorClass::Cyan),
        ];

        if ($state->isEditorOpen()) {
            new EditorRenderer()->render(
                $display,
                $state,
                $termHeight,
                $showCursor,
                $vPad,
                $hPadStr,
            );
            return;
        }

        $paneWidthPercent = $state->isSidebarVisible() ? 70 : 100;
        $leftPaneWidth = (int) ($termWidth * ($paneWidthPercent / 100));
        $innerWidth = max(1, $leftPaneWidth - 2 - $hPad * 2);

        // Render Input Box
        $inputData = new InputBoxRenderer()->render(
            $state,
            $innerWidth,
            $vPad,
            $hPadStr,
            $spanClass,
            $bgStyle,
        );
        $inputTuiLines = $inputData["lines"];
        $inputBoxHeight = $inputData["height"];

        // Render Chat Log
        $terminalDrawerHeight = $state->isTerminalDrawerOpen()
            ? (int) ($termHeight * 0.4)
            : 0;
        $visibleChatHeight = max(
            5,
            $termHeight - $inputBoxHeight - $terminalDrawerHeight - 2,
        );
        $sliceChatHeight = max(1, $visibleChatHeight - $vPad * 2);

        $chatWidget = new ChatRenderer()->render(
            $display,
            $state,
            $innerWidth,
            $sliceChatHeight,
            $vPad,
            $hPad,
            $hPadStr,
            $spanClass,
            $prefixes,
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
                ParagraphWidget::fromText(Text::fromLines(...$inputTuiLines)),
            ),
        ];

        // 3. Render Terminal Drawer if active
        if ($state->isTerminalDrawerOpen()) {
            $termLines = new TerminalRenderer()->render(
                $state,
                $terminalDrawerHeight,
                $vPad,
                $hPadStr,
                $spanClass,
                $bgStyle,
                $showCursor,
            );
            $leftWidgets[] = ReflectionHelper::createBlock(
                " Terminal ",
            )->widget(
                ParagraphWidget::fromText(Text::fromLines(...$termLines)),
            );
        }

        // 4. Delegate compilation
        new LayoutRenderer()->render(
            $display,
            $state,
            $leftWidgets,
            $termWidth,
            $termHeight,
            $inputBoxHeight,
            $terminalDrawerHeight,
            $vPad,
            $hPadStr,
            $spanClass,
        );
    }
}
