<?php
namespace Tests\Unit;

use App\State\AppState;
use PHPUnit\Framework\TestCase;

/**
 * Test class for the AppState class.
 *
 * @package Tests\Unit
 */
class AppStateTest extends TestCase
{
    /**
     * Test the initial state of the AppState.
     *
     * @return void
     */
    public function testInitialState(): void
    {
        $state = new AppState();

        $this->assertTrue($state->isRunning());
        $this->assertTrue($state->isMouseCaptureActive());
        $this->assertEquals("", $state->getInputText());
        $this->assertNotEmpty($state->getChatLog());
    }

    /* */
    public function testInputTextManagement(): void
    {
        $state = new AppState();
        $state->setInputText("Hello TUI");
        $this->assertEquals("Hello TUI", $state->getInputText());

        $state->appendInputText("!");
        $this->assertEquals("Hello TUI!", $state->getInputText());
    }

    /**
     * Test the chat log management.
     *
     * @return void
     */
    public function testChatLogManagement(): void
    {
        $state = new AppState();
        $state->addChatMessage("Hello TUI");
        $this->assertEquals(["Hello TUI"], $state->getChatLog());

        $state->addChatMessage("!");
        $this->assertEquals(["Hello TUI", "!"], $state->getChatLog());
    }

    /**
     * Test the chat log limit when adding more messages than allowed.
     *
     * @return void
     */
    public function testChatLogLimit(): void
    {
        $state = new AppState();

        // Push 1005 messages to test the 1000-message shift limit
        for ($i = 0; $i < 1005; $i++) {
            $state->addChatMessage("Message $i");
        }

        $log = $state->getChatLog();
        $this->assertLessThanOrEqual(1000, count($log));
        $this->assertEquals("Message 1004", end($log));
    }

    /**
     * Test the option toggling functionality.
     *
     * @return void
     */
    public function testOptionToggling(): void
    {
        $state = new AppState();
        $options = $state->getOptions();

        $this->assertFalse($options["cache"]["checked"]);

        $state->toggleOption("cache");
        $this->assertTrue($state->getOptions()["cache"]["checked"]);
    }
}
