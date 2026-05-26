<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

use InvalidArgumentException;

final readonly class CatalogEntry
{
    public function __construct(
        private Product $product,
        private int $stock,
    ) {
        if ($stock < 0) {
            throw new InvalidArgumentException('Stock cannot be negative.');
        }
    }

    public function product(): Product
    {
        return $this->product;
    }

    public function stock(): int
    {
        return $this->stock;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock === 0;
    }

    public function decrementStock(): self
    {
        if ($this->isOutOfStock()) {
            throw new InvalidArgumentException('Cannot decrement out-of-stock product.');
        }

        return new self($this->product, $this->stock - 1);
    }
}
