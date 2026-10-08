<?php

namespace Tests\Unit;

use App\State\AppState;
use PHPUnit\Framework\TestCase;

class AppStateTest extends TestCase
{
    private AppState $state;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = new AppState();
    }

    public function testInitialState(): void
    {
        $this->assertTrue($this->state->isRunning());
        $this->assertEquals("", $this->state->getInputText());
        $this->assertEquals("chat", $this->state->getInputFocus());
    }

    public function testInputTextManagement(): void
    {
        $this->state->setInputText("Hello");
        $this->assertEquals("Hello", $this->state->getInputText());

        $this->state->insertInputText(" World");
        $this->assertEquals("Hello World", $this->state->getInputText());
    }

    public function testChatLogManagement(): void
    {
        $welcomeMsg = "[System] Welcome to the Console! Type your prompt, or type / to see all available commands and shortcuts.";
        
        $this->assertEquals([$welcomeMsg], $this->state->getChatLog());

        $this->state->addChatMessage("Hello TUI");
        $this->assertEquals([$welcomeMsg, "Hello TUI"], $this->state->getChatLog());
    }

    public function testTerminalDrawerToggling(): void
    {
        $this->assertFalse($this->state->isTerminalDrawerOpen());
        $this->state->toggleTerminalDrawer();
        $this->assertTrue($this->state->isTerminalDrawerOpen());
    }

    public function testSidebarToggling(): void
    {
        $this->assertTrue($this->state->isSidebarVisible());
        $this->state->toggleSidebar();
        $this->assertFalse($this->state->isSidebarVisible());
    }
}
