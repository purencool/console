<?php
namespace App\State;

class ChatState
{
    private string $inputText = "";
    private int $inputCursor = 0;
    private array $chatLog = [
        "[System] Welcome to the Console! Type your prompt, or type / to see all available commands and shortcuts.",
    ];
    private int $scrollOffset = 0;
    private int $maxScroll = 0;
    private bool $autoScroll = true;

    // 💡 Declared encapsulated local command history stack properties
    private array $chatHistoryStack = [];
    private int $chatHistoryIdx = -1;

    public function getInputText(): string
    {
        return $this->inputText;
    }
    public function setInputText(string $text): void
    {
        $this->inputText = $text;
        $this->inputCursor = mb_strlen($text);
    }

    public function getInputCursor(): int
    {
        return $this->inputCursor;
    }
    public function setInputCursor(int $pos): void
    {
        $this->inputCursor = max(0, min(mb_strlen($this->inputText), $pos));
    }

    public function insertInputText(string $char): void
    {
        $left = mb_substr($this->inputText, 0, $this->inputCursor);
        $right = mb_substr($this->inputText, $this->inputCursor);
        $this->inputText = $left . $char . $right;
        $this->inputCursor += mb_strlen($char);
    }

    public function backspaceInputText(): void
    {
        if ($this->inputCursor > 0) {
            $left = mb_substr($this->inputText, 0, $this->inputCursor - 1);
            $right = mb_substr($this->inputText, $this->inputCursor);
            $this->inputText = $left . $right;
            $this->inputCursor--;
        }
    }

    public function getChatLog(): array
    {
        return $this->chatLog;
    }
    /**
     * Appends a fresh chat line and automatically offloads historical logs to disk.
     */
    public function addChatMessage(string $message): void
    {
        $this->chatLog[] = $message;
        $maxRuntimeLines = 1000;

        if (count($this->chatLog) > $maxRuntimeLines) {
            $offloadCount = count($this->chatLog) - $maxRuntimeLines + 100;
            $oldLogsChunk = array_slice($this->chatLog, 0, $offloadCount);
            $basePath = dirname(__DIR__, 2);
            $archiveDir = $basePath . "/.config";
            $archiveFile = $archiveDir . "/chat_archive.log";

            if (!file_exists($archiveDir)) {
                @mkdir($archiveDir, 0755, true);
            }

            $flatPayload = implode("\n", $oldLogsChunk) . "\n";
            @file_put_contents($archiveFile, $flatPayload, FILE_APPEND);
            $this->chatLog = array_slice($this->chatLog, $offloadCount);
            $timestamp = date("H:i:s");
            array_unshift(
                $this->chatLog,
                "[System] [{$timestamp}] Offloaded {$offloadCount} historical logs to '.config/chat_archive.log' safely.",
            );
        }
    }

    public function getScrollOffset(): int
    {
        return $this->scrollOffset;
    }
    public function setScrollOffset(int $offset): void
    {
        $this->scrollOffset = max(0, $offset);
    }

    public function getMaxScroll(): int
    {
        return $this->maxScroll;
    }
    public function setMaxScroll(int $maxScroll): void
    {
        $this->maxScroll = max(0, $maxScroll);
    }

    public function isAutoScroll(): bool
    {
        return $this->autoScroll;
    }
    public function setAutoScroll(bool $auto): void
    {
        $this->autoScroll = $auto;
    }

    /**
     * 💡 Records submitted prompt text to the internal historical stack memory buffer.
     */
    public function recordSubmission(string $input): void
    {
        if (trim($input) !== "") {
            array_unshift($this->chatHistoryStack, $input);
            $this->chatHistoryIdx = -1; // Reset indices configuration tracking offset pointer marker
        }
    }

    /**
     * 💡 Cycles active terminal input prompt display values backward/forward inside text fields.
     */
    public function cycleHistory(bool $up): void
    {
        if (empty($this->chatHistoryStack)) {
            return;
        }

        if ($up) {
            if ($this->chatHistoryIdx < count($this->chatHistoryStack) - 1) {
                $this->chatHistoryIdx++;
                $this->setInputText(
                    $this->chatHistoryStack[$this->chatHistoryIdx],
                );
                $this->setInputCursor(mb_strlen($this->getInputText()));
            }
        } else {
            if ($this->chatHistoryIdx > 0) {
                $this->chatHistoryIdx--;
                $this->setInputText(
                    $this->chatHistoryStack[$this->chatHistoryIdx],
                );
            } else {
                $this->chatHistoryIdx = -1;
                $this->setInputText("");
            }
            $this->setInputCursor(mb_strlen($this->getInputText()));
        }
    }
}
