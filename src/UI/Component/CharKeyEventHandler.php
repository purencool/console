<?php
namespace App\UI\Component;

use App\State\AppState;
use PhpTui\Term\Event\CharKeyEvent;
use PhpTui\Term\KeyModifiers;

class CharKeyEventHandler
{
    /**
     * Routes alphanumeric keyboard inputs, processing fast paste transactions instantly
     * without blocking sequential normal typing.
     *
     * @param array<string, object> $services Centralised system dependency registry
     */
    public function handle(CharKeyEvent $event, array $services): bool
    {
        /** @var AppState $state */
        $state = $services["state"];

        // Defer control modifiers to global action orchestrator layers
        $isCtrl =
            ($event->modifiers & KeyModifiers::CONTROL) ===
            KeyModifiers::CONTROL;
        if ($isCtrl) {
            return false;
        }

        $textBuffer = $event->char;

        // 💡 THE FIX: Check if the arriving byte sequence is the Bracketed Paste opening marker flag
        if ($textBuffer === "\x1b" || str_contains($textBuffer, "[200~")) {
            // Siphon the remaining clipboard chunk instantly via non-blocking transaction
            stream_set_blocking(STDIN, false);
            $remainingChunk = fread(STDIN, 8192);
            stream_set_blocking(STDIN, true);

            if ($remainingChunk !== false && $remainingChunk !== "") {
                $textBuffer .= $remainingChunk;
            }
        }

        // Clean up modern bracketed paste wrapper boundaries out of the character pool string
        $textBuffer = str_replace(
            ["\x1b[200~", "\x1b[201~", "[200~", "[201~", "\x1b"],
            "",
            $textBuffer,
        );

        if ($textBuffer === "") {
            return true;
        }

        // Validate and insert printable text block strings natively into focus layers
        if (ord($textBuffer) >= 32 && $textBuffer !== "\x7f") {
            if (
                $state->isTerminalDrawerOpen() &&
                $state->getInputFocus() === "terminal"
            ) {
                $state->insertActiveTerminalInput($textBuffer);
            } else {
                // Otherwise, format internal newline breaks as system chat spacers and append to main field
                $cleanChatInputText = str_replace("\n", "/n ", $textBuffer);
                $state->insertInputText($cleanChatInputText);
            }
            return true;
        }

        return false;
    }
}
