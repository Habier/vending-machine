<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

use InvalidArgumentException;

final readonly class Coin
{
    public function __construct(private int $cents)
    {
        if ($cents <= 0) {
            throw new InvalidArgumentException('Coin denomination must be positive.');
        }
    }

    public function cents(): int
    {
        return $this->cents;
    }
}
