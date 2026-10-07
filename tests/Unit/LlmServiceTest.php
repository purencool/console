<?php
namespace Tests\Unit;

use App\Services\LlmService;
use PHPUnit\Framework\TestCase;

/**
 * This test class is designed to verify the functionality of the LlmService class, which is responsible for resolving and executing slash commands based on the provided command string and log message.
 *
 * @package Tests\Unit
 */
class LlmServiceTest extends TestCase
{
    /**
     * This test method verifies that the LlmService class correctly resolves a slash command by stripping the leading slash and executing it.
     *
     * @return void
     */
    public function testResolvesSlashCommands(): void
    {
        $service = new LlmService("python chat.py ", "php artisan skills");

        [$cmd, $logMsg] = $service->resolveCommand("/skills");

        $this->assertEquals("php artisan skills", $cmd);
        $this->assertEquals("[System] Running: php artisan skills", $logMsg);
    }

    /**
     * This test method verifies that the LlmService class correctly resolves a standard slash command by stripping the leading slash.
     *
     * @return void
     */
    public function testResolvesStandardSlashCommand(): void
    {
        $service = new LlmService("python chat.py ", "php artisan skills");

        // Any other slash command like /status strips the leading slash
        [$cmd, $logMsg] = $service->resolveCommand("/status");

        $this->assertEquals("status", $cmd);
        $this->assertEquals("[System] Running: status", $logMsg);
    }

    /**
     * This test method verifies that the LlmService class correctly resolves a LLM prompt command by stripping the leading slash.
     *
     * @return void
     */
    public function testResolvesLlmPromptCommand(): void
    {
        $service = new LlmService("python chat.py ", "php artisan skills");

        [$cmd, $logMsg] = $service->resolveCommand("Hello AI");

        $this->assertStringContainsString("python chat.py", $cmd);
        $this->assertStringContainsString("Hello AI", $cmd);
        $this->assertEquals("[Agent] Thinking...", $logMsg);
    }
}
