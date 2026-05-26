<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

final readonly class InsertCoinResult
{
    private function __construct(
        public bool $accepted,
        public ?Coin $returnedCoin,
    ) {
    }

    public static function accepted(): self
    {
        return new self(true, null);
    }

    public static function rejected(Coin $returnedCoin): self
    {
        return new self(false, $returnedCoin);
    }
}
