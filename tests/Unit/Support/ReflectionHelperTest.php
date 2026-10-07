<?php
namespace Tests\Unit\Support;

use App\Support\ReflectionHelper;
use PHPUnit\Framework\TestCase;

/**
 * Test class for ReflectionHelper.
 *
 * @package Tests\Unit\Support
 */
class ReflectionHelperTest extends TestCase
{
    /**
     * Test the class instantiation.
     *
     * @return void
     */
    public function testClassInstantiation(): void
    {
        // TODO: Replace with concrete assertions for ReflectionHelper
        $this->assertTrue(class_exists(ReflectionHelper::class));
    }
}
