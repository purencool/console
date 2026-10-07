<?php
namespace Tests\Unit\Support;

use App\Support\TerminalSetup;
use PHPUnit\Framework\TestCase;

/**
 * Test the TerminalSetup class.
 *
 * @package Tests\Unit\Support
 */
class TerminalSetupTest extends TestCase
{
    /**
     * Test that the TerminalSetup class exists.
     *
     * @return void
     */
    public function testTerminalSetupClassExists(): void
    {
        $this->assertTrue(class_exists(TerminalSetup::class));
    }
}
