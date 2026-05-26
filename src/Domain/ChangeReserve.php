<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

final readonly class ChangeReserve
{
    public function __construct(
        private Coins $coins,
    ) {
    }

    public static function empty(): self
    {
        return new self(Coins::empty());
    }

    public static function fromCoins(Coins $coins): self
    {
        return new self($coins);
    }

    public static function fromCents(int ...$cents): self
    {
        return new self(Coins::fromCents(...$cents));
    }

    public function coins(): Coins
    {
        return $this->coins;
    }

    public function total(): Money
    {
        return $this->coins->sum();
    }

    public function remove(Coins $dispensedChange): self
    {
        return new self($this->coins->remove($dispensedChange));
    }

    public function merge(Coins $depositedCoins): self
    {
        return new self($this->coins->merge($depositedCoins));
    }

    public function sortedCoinsDescending(): Coins
    {
        return $this->coins->sortedDescending();
    }
}
