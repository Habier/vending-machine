<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Result;

use VendingMachine\Domain\Money;
use VendingMachine\Domain\Product;

final readonly class InsufficientFunds implements ProductSelectionResult
{
    public function __construct(
        public Product $product,
        public Money $missingAmount,
    ) {
    }
}
