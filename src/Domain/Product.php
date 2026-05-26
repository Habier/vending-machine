<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

use InvalidArgumentException;

final readonly class Product
{
    public function __construct(
        private ProductSelection $selection,
        private string $name,
        private Money $price,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Product name cannot be empty.');
        }
    }

    public function selection(): ProductSelection
    {
        return $this->selection;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }
}
