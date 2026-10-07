<?php
namespace Tests\Unit\UI;

use App\UI\EventHandler;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the EventHandler class.
 *
 * @package Tests\Unit\UI
 */
class EventHandlerTest extends TestCase
{
    /**
     * Test that the EventHandler class exists.
     *
     * @return void
     */
    public function testEventHandlerClassExists(): void
    {
        $this->assertTrue(class_exists(EventHandler::class));
    }
}
