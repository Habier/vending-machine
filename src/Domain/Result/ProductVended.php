<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Result;

use VendingMachine\Domain\Coins;
use VendingMachine\Domain\Product;

final readonly class ProductVended implements ProductSelectionResult
{
    public function __construct(
        public Product $product,
        public Coins $change,
    ) {
    }
}
