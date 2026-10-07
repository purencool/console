<?php
namespace Tests\Unit;

use App\Application;
use PHPUnit\Framework\TestCase;

/**
 * Test class for the Application.
 *
 * @package Tests\Unit
 */
class ApplicationTest extends TestCase
{
    /**
     * Test that the Application class can be instantiated.
     *
     * @return void
     */
    public function testClassInstantiation(): void
    {
        // TODO: Replace with concrete assertions for Application
        $this->assertTrue(class_exists(Application::class));
    }
}
