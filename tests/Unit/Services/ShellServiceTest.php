<?php
namespace Tests\Unit\Services;

use App\Services\ShellService;
use PHPUnit\Framework\TestCase;

/*
 * @covers ShellService
 * @group services
 */
class ShellServiceTest extends TestCase
{
    /**
     * Test that the class is instantiated correctly.
     */
    public function testClassInstantiation(): void
    {
        // TODO: Replace with concrete assertions for ShellService
        $this->assertTrue(class_exists(ShellService::class));
    }
}
