<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Result;

use VendingMachine\Domain\Product;

final readonly class ExactChangeUnavailable implements ProductSelectionResult
{
    public function __construct(public Product $product)
    {
    }
}
