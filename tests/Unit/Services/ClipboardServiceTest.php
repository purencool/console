<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\ClipboardService;

class ClipboardServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ClipboardService::class));
    }
}
