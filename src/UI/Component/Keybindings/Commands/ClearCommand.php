<?php
namespace App\UI\Component\Keybindings\Commands;

use App\State\AppState;

class ClearCommand implements CommandHandlerInterface
{
    public function execute(array $services, string $argument): bool
    {
        /** @var AppState $state */
        $state = $services["state"];
        $state->setInputText("");

        $chatLogArray = $state->getChatLog();
        $timestamp = date("Ymd_His");
        $humanTime = date("Y-m-d H:i:s");

        if (!empty($chatLogArray)) {
            $basePath = dirname(__DIR__, 4);
            $archiveDir = $basePath . "/.config/archives";
            $archiveFile = $archiveDir . "/chat_archive_{$timestamp}.log";

            if (!file_exists($archiveDir)) {
                @mkdir($archiveDir, 0755, true);
            }

            $flatPayload =
                "--- CHAT LOG SNAPSHOT RECORDED AT [{$humanTime}] ---\n" .
                implode("\n", $chatLogArray) .
                "\n";
            @file_put_contents($archiveFile, $flatPayload);
        }

        if (method_exists($state, "clearChatLog")) {
            $state->clearChatLog();
        } else {
            $ref = new \ReflectionProperty($state, "chatLog");
            $ref->setAccessible(true);
            $ref->setValue($state, []);
        }

        $state->setScrollOffset(0);
        $state->setAutoScroll(true);
        $state->addChatMessage(
            "[System] Chat memory wiped. Session log exported to '.config/archives/chat_archive_{$timestamp}.log'.",
        );

        return true;
    }
}
