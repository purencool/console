<?php
namespace App\UI;

use App\State\AppState;
use App\Support\ReflectionHelper;
use PhpTui\Tui\Extension\Core\Widget\GridWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Text\Line;
use PhpTui\Tui\Text\Text;

class Renderer
{
    public static function render(
        $display,
        AppState $state,
        int $maxScroll,
    ): void {
        [
            $dirHorizontal,
            $dirVertical,
        ] = ReflectionHelper::getDirectionClasses();
        $constraintClass = ReflectionHelper::getConstraintClass();

        $viewport = method_exists($display, "viewportArea")
            ? $display->viewportArea()
            : null;
        $termWidth = $viewport ? $viewport->width : 100;
        $termHeight = $viewport ? $viewport->height : 30;

        $showCursor = (int) (microtime(true) * 2) % 2 === 0;

        // --- PADDING CONFIGURATION (~3px equivalent) ---
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
        $lineNumStyle = $styleClass::default()->fg($colorClass::DarkGray);

        $prefixes = [
            "[System]" => $styleClass::default()->fg($colorClass::Yellow),
            "[Agent]" => $styleClass::default()->fg($colorClass::Green),
            "[Error]" => $styleClass::default()->fg($colorClass::Red),
            "You:" => $styleClass::default()->fg($colorClass::Cyan),
        ];

        // --- RENDER EDITOR MODE ---
        if ($state->isEditorOpen()) {
            $lines = $state->getEditorLines();
            $row = $state->getEditorRow();
            $col = $state->getEditorCol();

            $visibleLines = max(1, $termHeight - 2 - $vPad * 2);
            $scrollY = $state->getEditorScrollY();
            if ($row < $scrollY) {
                $scrollY = $row;
            } elseif ($row >= $scrollY + $visibleLines) {
                $scrollY = $row - $visibleLines + 1;
            }
            $state->setEditorScrollY($scrollY);

            $renderLines = array_slice($lines, $scrollY, $visibleLines);
            $tuiLines = [];

            for ($i = 0; $i < $vPad; $i++) {
                $tuiLines[] = Line::fromString("");
            }

            // Calculate width needed for line numbers (e.g., 3 spaces for up to 999 lines)
            $totalLines = count($lines);
            $lineNumWidth = max(3, strlen((string) $totalLines));

            $selBgStyle = $styleClass::default()->bg($colorClass::Blue);
            $sel = $state->getEditorSelection();

            foreach ($renderLines as $idx => $l) {
                $actualLineNum = $scrollY + $idx + 1;
                $lineNumStr =
                    str_pad(
                        (string) $actualLineNum,
                        $lineNumWidth,
                        " ",
                        STR_PAD_LEFT,
                    ) . " │ ";

                if ($idx === $row - $scrollY) {
                    $cCol = $col;
                    $char =
                        $cCol >= mb_strlen($l)
                            ? "\x27"
                            : mb_substr($l, $cCol, 1);
                    $left = mb_substr($l, 0, $cCol);
                    $right = mb_substr($l, $cCol + 1);

                    $spans = [
                        $spanClass::fromString($hPadStr),
                        $spanClass
                            ::fromString($lineNumStr)
                            ->style($lineNumStyle),
                    ];

                    if ($left !== "\x27") {
                        $spans[] = $spanClass::fromString($left);
                    }

                    if ($showCursor) {
                        $spans[] = $spanClass
                            ::fromString($char)
                            ->style($bgStyle);
                    } else {
                        $spans[] = $spanClass::fromString($char);
                    }

                    if ($right !== "\x27") {
                        $spans[] = $spanClass::fromString($right);
                    }
                    $tuiLines[] = Line::fromSpans(...$spans);
                } else {
                    $spans = [
                        $spanClass::fromString($hPadStr),
                        $spanClass
                            ::fromString($lineNumStr)
                            ->style($lineNumStyle),
                        $spanClass::fromString($l),
                    ];
                    $tuiLines[] = Line::fromSpans(...$spans);
                }
            }

            for ($i = 0; $i < $vPad; $i++) {
                $tuiLines[] = Line::fromString("");
            }

            $statusText = $state->getEditorStatus() ?: "Ready";
            // Added Row and Col tracker to the title
            $title = sprintf(
                " Editor: %s [Row: %d, Col: %d] [Ctrl+S: Save | Esc: Close] - %s ",
                $state->getEditorFilePath(),
                $row + 1,
                $col + 1,
                $statusText,
            );

            $display->draw(
                GridWidget::default()
                    ->constraints($constraintClass::percentage(100))
                    ->widgets(
                        ReflectionHelper::createBlock($title)->widget(
                            ParagraphWidget::fromText(
                                Text::fromLines(...$tuiLines),
                            ),
                        ),
                    ),
            );
            return;
        }
        // --- END EDITOR MODE ---

        $paneWidthPercent = $state->isSidebarVisible() ? 70 : 100;
        $leftPaneWidth = (int) ($termWidth * ($paneWidthPercent / 100));

        $innerWidth = max(1, $leftPaneWidth - 2 - $hPad * 2);

        // --- MAIN INPUT BOX ---
        $text = $state->getInputText();
        $pos = $state->getInputCursor();
        $cursorChar =
            $pos >= mb_strlen($text) ? " " : mb_substr($text, $pos, 1);

        $left = mb_substr($text, 0, $pos);
        $right = mb_substr($text, $pos + 1);

        $chatDisplayInput = $left . "\x7F" . $right;
        $chatDisplayInput = str_replace("/n", "\n", $chatDisplayInput);

        $wrappedInputLines = [];
        foreach (explode("\n", $chatDisplayInput) as $rawLine) {
            if ($rawLine === "") {
                $wrappedInputLines[] = "";
            } else {
                foreach (
                    ReflectionHelper::wrapText($rawLine, $innerWidth)
                    as $wl
                ) {
                    $wrappedInputLines[] = $wl;
                }
            }
        }

        $inputTuiLines = [];
        for ($i = 0; $i < $vPad; $i++) {
            $inputTuiLines[] = Line::fromString("");
        }

        foreach ($wrappedInputLines as $wl) {
            if (str_contains($wl, "\x7F")) {
                $parts = explode("\x7F", $wl);
                $spans = [$spanClass::fromString($hPadStr)];
                if ($parts[0] !== "") {
                    $spans[] = $spanClass::fromString($parts[0]);
                }

                if ($state->getInputFocus() === "chat" && $showCursor) {
                    $spans[] = $spanClass
                        ::fromString($cursorChar)
                        ->style($bgStyle);
                } else {
                    $spans[] = $spanClass::fromString($cursorChar);
                }

                if (isset($parts[1]) && $parts[1] !== "") {
                    $spans[] = $spanClass::fromString($parts[1]);
                }
                $inputTuiLines[] = Line::fromSpans(...$spans);
            } else {
                $inputTuiLines[] = Line::fromString($hPadStr . $wl);
            }
        }

        for ($i = 0; $i < $vPad; $i++) {
            $inputTuiLines[] = Line::fromString("");
        }

        $inputBoxHeight = min(
            15,
            max(3 + $vPad * 2, count($inputTuiLines) + 2),
        );

        // --- CHAT LOG ---
        $wrappedChatLog = [];
        $chatLogArray = $state->getChatLog();
        $chatLogCount = count($chatLogArray);

        foreach ($chatLogArray as $idx => $logLine) {
            foreach (ReflectionHelper::wrapText($logLine, $innerWidth) as $wl) {
                $wrappedChatLog[] = $wl;
            }
            if ($idx < $chatLogCount - 1) {
                $wrappedChatLog[] = "";
            }
        }

        if ($state->isProcessing()) {
            $spinnerFrames = ["⠋", "⠙", "⠹", "⠸", "⠼", "⠴", "⠦", "⠧", "⠇", "⠏"];
            $spinner =
                $spinnerFrames[
                    (int) (microtime(true) * 10) % count($spinnerFrames)
                ];
            $wrappedChatLog[] = "";
            $wrappedChatLog[] = sprintf(
                "%s [System] Processing request... (%ss)",
                $spinner,
                $state->getProcessingDuration(),
            );
        }

        $terminalDrawerHeight = $state->isTerminalDrawerOpen()
            ? (int) ($termHeight * 0.4)
            : 0;

        $visibleChatHeight = max(
            5,
            $termHeight - $inputBoxHeight - $terminalDrawerHeight - 2,
        );
        $sliceChatHeight = max(1, $visibleChatHeight - $vPad * 2);
        $visibleLog = array_slice(
            $wrappedChatLog,
            $state->getScrollOffset(),
            $sliceChatHeight,
        );

        $chatLines = [];
        for ($i = 0; $i < $vPad; $i++) {
            $chatLines[] = Line::fromString("");
        }

        foreach ($visibleLog as $msg) {
            if ($msg === "") {
                $chatLines[] = Line::fromString("");
                continue;
            }

            $lineStr = $hPadStr . $msg;
            $matched = false;

            foreach ($prefixes as $prefix => $style) {
                if (str_contains($msg, $prefix)) {
                    $pos = mb_strpos($lineStr, $prefix);
                    $left = mb_substr($lineStr, 0, $pos);
                    $right = mb_substr($lineStr, $pos + mb_strlen($prefix));

                    $spans = [];
                    if ($left !== "") {
                        $spans[] = $spanClass::fromString($left);
                    }
                    $spans[] = $spanClass::fromString($prefix)->style($style);
                    if ($right !== "") {
                        $spans[] = $spanClass::fromString($right);
                    }

                    $chatLines[] = Line::fromSpans(...$spans);
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $chatLines[] = Line::fromString($lineStr);
            }
        }
        for ($i = 0; $i < $vPad; $i++) {
            $chatLines[] = Line::fromString("");
        }

        $chatTitle = $state->getScannedPath()
            ? " Chat Log [Path Active: " .
                basename($state->getScannedPath()) .
                "] "
            : " Chat Log [Ctrl+T: Terminal | Ctrl+S: Sidebar] ";
        $chatBoxTitle = $state->isProcessing()
            ? " Input [Processing Request...] "
            : " Input (Type / for help, Enter to submit) ";

        $leftWidgets = [
            ReflectionHelper::createBlock($chatTitle)->widget(
                ParagraphWidget::fromText(Text::fromLines(...$chatLines)),
            ),
            ReflectionHelper::createBlock($chatBoxTitle)->widget(
                ParagraphWidget::fromText(Text::fromLines(...$inputTuiLines)),
            ),
        ];

        // --- TERMINAL DRAWER ---
        if ($state->isTerminalDrawerOpen()) {
            $visibleTermHeight = max(2, $terminalDrawerHeight - 2);
            $sliceTermHeight = max(1, $visibleTermHeight - $vPad * 2 - 1);
            $maxTermScroll = max(
                0,
                count($state->getTerminalBuffer()) - $sliceTermHeight,
            );
            $termScroll = min(
                max(0, $state->getTerminalScrollOffset()),
                $maxTermScroll,
            );
            $state->setTerminalScrollOffset($termScroll);

            $visibleTermBuffer = array_slice(
                $state->getTerminalBuffer(),
                $termScroll,
                $sliceTermHeight,
            );

            $termLines = [];
            for ($i = 0; $i < $vPad; $i++) {
                $termLines[] = Line::fromString("");
            }
            foreach ($visibleTermBuffer as $l) {
                $termLines[] = Line::fromString($hPadStr . $l);
            }

            $termText = $state->getActiveTerminalInput();
            $termPos = $state->getTerminalCursor();
            $char =
                $termPos >= mb_strlen($termText)
                    ? " "
                    : mb_substr($termText, $termPos, 1);
            $left = mb_substr($termText, 0, $termPos);
            $right = mb_substr($termText, $termPos + 1);

            $spans = [$spanClass::fromString($hPadStr . "$ ")];
            if ($left !== "") {
                $spans[] = $spanClass::fromString($left);
            }

            if ($state->getInputFocus() === "terminal" && $showCursor) {
                $spans[] = $spanClass::fromString($char)->style($bgStyle);
            } else {
                $spans[] = $spanClass::fromString($char);
            }

            if ($right !== "") {
                $spans[] = $spanClass::fromString($right);
            }

            $termLines[] = Line::fromSpans(...$spans);
            for ($i = 0; $i < $vPad; $i++) {
                $termLines[] = Line::fromString("");
            }

            $leftWidgets[] = ReflectionHelper::createBlock(
                " Terminal Drawer (Tab to focus) ",
            )->widget(
                ParagraphWidget::fromText(Text::fromLines(...$termLines)),
            );
        }

        $constraints = [
            $constraintClass::min(0),
            $constraintClass::length($inputBoxHeight),
        ];
        if ($state->isTerminalDrawerOpen()) {
            $constraints[] = $constraintClass::length($terminalDrawerHeight);
        }

        $leftGrid = GridWidget::default()
            ->direction($dirVertical)
            ->constraints(...$constraints)
            ->widgets(...$leftWidgets);

        // --- SIDEBAR ---
        if ($state->isSidebarVisible()) {
            $rawControlLines = [
                "Active Scanned Path:",
                $state->getScannedPath() ?? "None",
                "",
                "[ SUBMIT ARTISAN COMMAND ]",
            ];

            $controlLines = [];
            for ($i = 0; $i < $vPad; $i++) {
                $controlLines[] = Line::fromString("");
            }
            foreach ($rawControlLines as $rcl) {
                $controlLines[] = Line::fromString($hPadStr . $rcl);
            }
            for ($i = 0; $i < $vPad; $i++) {
                $controlLines[] = Line::fromString("");
            }

            $display->draw(
                GridWidget::default()
                    ->direction($dirHorizontal)
                    ->constraints(
                        $constraintClass::percentage(70),
                        $constraintClass::percentage(30),
                    )
                    ->widgets(
                        $leftGrid,
                        ReflectionHelper::createBlock(
                            " Controls [Ctrl+S to hide] ",
                        )->widget(
                            ParagraphWidget::fromText(
                                Text::fromLines(...$controlLines),
                            ),
                        ),
                    ),
            );
        } else {
            $display->draw(
                GridWidget::default()
                    ->direction($dirHorizontal)
                    ->constraints($constraintClass::percentage(100))
                    ->widgets($leftGrid),
            );
        }
    }
}
