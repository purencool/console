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
    public function addChatMessage(string $msg): void
    {
        $this->chatLog[] = $msg;
        if (count($this->chatLog) > 1000) {
            $this->chatLog = array_slice($this->chatLog, -1000);
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
}
