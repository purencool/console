<?php
namespace App\Services;

/**
 * LlmService class
 *
 * @package App\Services
 */
class LlmService
{
    /** @var string */
    private string $llmChatCmd;

    /** @var string */
    private string $skillsCmd;

    /**
     * @param string $llmChatCmd
     * @param string $skillsCmd
     *
     * @throws \Exception
     */
    public function __construct(string $llmChatCmd, string $skillsCmd)
    {
        $this->llmChatCmd = $llmChatCmd;
        $this->skillsCmd = $skillsCmd;
    }

    /**
     * ResolveCommand completes the following actions:
     *  1. If the command starts with a `/`, it executes the
     *  corresponding command.
     *  2. Otherwise, it sends the prompt to the LLM and returns
     *  the response along with a status message.
     *
     * @param string $promptText
     *
     * @throws \Exception
     *
     * @return array{string, string}
     */
    public function resolveCommand(string $promptText): array
    {
        if (str_starts_with($promptText, "/")) {
            $cmd =
                $promptText === "/skills"
                    ? $this->skillsCmd
                    : substr($promptText, 1);
            return [$cmd, "[System] Running: $cmd"];
        } else {
            $jsonPayload = json_encode(
                ["prompt" => $promptText],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
            $cmd = $this->llmChatCmd . escapeshellarg($jsonPayload);
            return [$cmd, "[Agent] Thinking..."];
        }
    }
}
