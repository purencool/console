<?php
namespace App\State;

use App\Services\ConfigManager;
/**
 * Class AppState
 *
 * --- SYSTEM CONTEXT PROXIES ---
 * @method bool isRunning()
 * @method void stop()
 * @method string getInputFocus()
 * @method void setInputFocus(string $focus)
 * @method void toggleInputFocus()
 * @method bool isSidebarVisible()
 * @method void toggleSidebar()
 * @method string|null getScannedPath()
 * @method void setScannedPath(string|null $path)
 * @method bool isProcessing()
 * @method void setProcessing(bool $processing)
 * @method float getProcessingDuration()
 * @method array getOptions()
 * @method void toggleOption(string $key)
 * @method void addHistory(string $input)
 * @method void setActiveAiService(string $serviceId)
 * @method void historyUp(callable $setInputText, callable $getInputText)
 * @method void historyDown(callable $setInputText)
 *
 * --- CHAT CONTEXT PROXIES ---
 * @method string getInputText()
 * @method void setInputText(string $text)
 * @method int getInputCursor()
 * @method void setInputCursor(int $pos)
 * @method void insertInputText(string $char)
 * @method void backspaceInputText()
 * @method array getChatLog()
 * @method void addChatMessage(string $msg)
 * @method int getScrollOffset()
 * @method void setScrollOffset(int $offset)
 * @method int getMaxScroll()
 * @method void setMaxScroll(int $maxScroll)
 * @method bool isAutoScroll()
 * @method void setAutoScroll(bool $auto)
 * @method void recordChatSubmission(string $input)
 * @method void cycleChatHistory(bool $up)
 *
 * --- TERMINAL CONTEXT PROXIES ---
 * @method string getActiveTerminalInput()
 * @method void setActiveTerminalInput(string $input)
 * @method int getTerminalCursor()
 * @method void setTerminalCursor(int $pos)
 * @method void insertActiveTerminalInput(string $char)
 * @method void backspaceActiveTerminalInput()
 * @method array getTerminalBuffer()
 * @method void addTerminalOutput(string $line)
 * @method int getTerminalScrollOffset()
 * @method void setTerminalScrollOffset(int $offset)
 * @method bool isTerminalDrawerOpen()
 * @method void toggleTerminalDrawer()
 * @method void recordTermSubmission(string $input)
 * @method void cycleTermHistory(bool $up)
 *
 * --- EDITOR CONTEXT PROXIES ---
 * @method bool isEditorOpen()
 * @method void setEditorOpen(bool $open)
 * @method string getEditorFilePath()
 * @method void setEditorFilePath(string $path)
 * @method array getEditorLines()
 * @method void setEditorLines(array $lines)
 * @method int getEditorRow()
 * @method void setEditorRow(int $row)
 * @method int getEditorCol()
 * @method void setEditorCol(int $col)
 * @method string getEditorStatus()
 * @method void setEditorStatus(string $status)
 * @method void insertEditorChar(string $char)
 * @method void backspaceEditorChar()
 * @method void enterEditorLine()
 * @method int getEditorScrollY()
 * @method void setEditorScrollY(int $scrollY)
 */
class AppState
{
    private ChatState $chat;
    private TerminalState $terminal;
    private EditorState $editor;
    private SystemState $system;

    public function __construct(ConfigManager $configManager)
    {
        $this->chat = new ChatState();
        $this->terminal = new TerminalState();
        $this->editor = new EditorState();
        $this->system = new SystemState($configManager);
    }

    public function chat(): ChatState
    {
        return $this->chat;
    }
    public function terminal(): TerminalState
    {
        return $this->terminal;
    }
    public function editor(): EditorState
    {
        return $this->editor;
    }
    public function system(): SystemState
    {
        return $this->system;
    }

    /**
     * Intercepts missing methods and forwards them to the correct sub-state.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if ($method === "historyUp") {
            $this->system->historyUp(
                fn($text) => $this->setInputText($text),
                fn() => $this->getInputText(),
            );
            return null;
        }

        if ($method === "historyDown") {
            $this->system->historyDown(fn($text) => $this->setInputText($text));
            return null;
        }

        // Standard dynamic fallback lookup loop
        foreach (
            [$this->system, $this->chat, $this->terminal, $this->editor]
            as $state
        ) {
            if (method_exists($state, $method)) {
                return call_user_func_array([$state, $method], $arguments);
            }
        }

        throw new \BadMethodCallException(
            "Method {$method} does not exist on AppState or its sub-states.",
        );
    }
}
