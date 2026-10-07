<?php
namespace Tests\Unit\UI;

use App\UI\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Test class for the Renderer class.
 *
 * @package Tests\Unit\UI
 */
class RendererTest extends TestCase
{
    /**
     * Test that the Renderer class exists.
     *
     * @return void
     */
    public function testRendererClassExists(): void
    {
        $this->assertTrue(class_exists(Renderer::class));
    }
}
