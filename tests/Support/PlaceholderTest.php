<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use VendingMachine\Support\Placeholder;

#[CoversClass(Placeholder::class)]
final class PlaceholderTest extends TestCase
{
    public function testPlaceholderKeepsToolingGreen(): void
    {
        self::assertTrue(Placeholder::alive());
    }
}
