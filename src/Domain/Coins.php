<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

use InvalidArgumentException;

final readonly class Coins
{
    /**
     * @var list<Coin>
     */
    private array $coins;

    /**
     * @param list<Coin> $coins
     */
    public function __construct(array $coins = [])
    {
        $this->coins = $coins;
    }

    public static function empty(): self
    {
        return new self();
    }

    public static function fromCents(int ...$cents): self
    {
        $coins = [];

        foreach ($cents as $value) {
            $coins[] = new Coin($value);
        }

        return new self($coins);
    }

    /**
     * @return list<Coin>
     */
    public function all(): array
    {
        return $this->coins;
    }

    /**
     * @return list<int>
     */
    public function values(): array
    {
        return array_map(static fn (Coin $coin): int => $coin->cents(), $this->coins);
    }

    public function with(Coin $coin): self
    {
        $coins = $this->coins;
        $coins[] = $coin;

        return new self($coins);
    }

    public function merge(self $other): self
    {
        return new self([...$this->coins, ...$other->coins]);
    }

    public function sum(): Money
    {
        return new Money(array_sum($this->values()));
    }

    public function isEmpty(): bool
    {
        return $this->coins === [];
    }

    public function sortedDescending(): self
    {
        $coins = $this->coins;

        usort(
            $coins,
            static fn (Coin $left, Coin $right): int => $right->cents() <=> $left->cents(),
        );

        return new self($coins);
    }

    public function remove(self $coinsToRemove): self
    {
        $remaining = $this->coins;

        foreach ($coinsToRemove->coins as $coinToRemove) {
            $removed = false;

            foreach ($remaining as $index => $coin) {
                if ($coin->cents() !== $coinToRemove->cents()) {
                    continue;
                }

                unset($remaining[$index]);
                $removed = true;

                break;
            }

            if (!$removed) {
                throw new InvalidArgumentException('Cannot remove coins that are not available.');
            }
        }

        return new self(array_values($remaining));
    }
}
