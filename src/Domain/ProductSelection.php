<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

use InvalidArgumentException;

final readonly class ProductSelection
{
    public function __construct(private string $code)
    {
        if ($code === '') {
            throw new InvalidArgumentException('Product selection cannot be empty.');
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
