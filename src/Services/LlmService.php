<?php
namespace App\Services;

/**
 * LlmService class
 *
 * @package App\Services
 */
class LlmService
{
    /**
     *
     * @throws \Exception
     */
    public function __construct() {}

    /**
     * Query completes the following actions:
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
    public function query(string $promptText): array
    {
        return [
            "system" => "",
            "user" => "",
            "request" => "",
        ];
    }
}
