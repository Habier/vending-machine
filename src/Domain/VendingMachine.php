<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

use VendingMachine\Domain\Result\ExactChangeUnavailable;
use VendingMachine\Domain\Result\InsufficientFunds;
use VendingMachine\Domain\Result\OutOfStock;
use VendingMachine\Domain\Result\ProductSelectionResult;
use VendingMachine\Domain\Result\ProductVended;
use VendingMachine\Domain\Result\UnknownProductSelection;

final class VendingMachine
{
    /**
     * @var array<int, true>
     */
    private const ACCEPTED_COIN_DENOMINATIONS = [
        5 => true,
        10 => true,
        25 => true,
        100 => true,
    ];

    private Coins $insertedCoins;

    public function __construct(
        private Catalog $catalog,
        private Coins $availableChange,
    ) {
        $this->insertedCoins = Coins::empty();
    }

    /**
     * @return list<int>
     */
    public static function acceptedCoinDenominations(): array
    {
        return array_keys(self::ACCEPTED_COIN_DENOMINATIONS);
    }

    /**
     * Replaces the machine catalog and change reserve while preserving the current customer's inserted coins.
     */
    public function service(Catalog $catalog, Coins $availableChange): void
    {
        $this->catalog = $catalog;
        $this->availableChange = $availableChange;
    }

    public function insertCoin(Coin $coin): InsertCoinResult
    {
        if (!isset(self::ACCEPTED_COIN_DENOMINATIONS[$coin->cents()])) {
            return InsertCoinResult::rejected($coin);
        }

        $this->insertedCoins = $this->insertedCoins->with($coin);

        return InsertCoinResult::accepted();
    }

    public function returnCoins(): Coins
    {
        $returnedCoins = $this->insertedCoins;
        $this->insertedCoins = Coins::empty();

        return $returnedCoins;
    }

    public function insertedMoney(): Money
    {
        return $this->insertedCoins->sum();
    }

    public function catalog(): Catalog
    {
        return $this->catalog;
    }

    public function availableChange(): Coins
    {
        return $this->availableChange;
    }

    /**
     * Attempts to vend the selected product.
     *
     * Expected business failures are returned as explicit result objects rather than thrown as exceptions.
     */
    public function selectProduct(ProductSelection $selection): ProductSelectionResult
    {
        $entry = $this->catalog->find($selection);

        if ($entry === null) {
            return new UnknownProductSelection($selection);
        }

        $product = $entry->product();
        $insertedMoney = $this->insertedCoins->sum();

        if ($insertedMoney->isLessThan($product->price())) {
            return new InsufficientFunds($product, $product->price()->subtract($insertedMoney));
        }

        if ($entry->isOutOfStock()) {
            return new OutOfStock($product);
        }

        $changeAmount = $insertedMoney->subtract($product->price());
        $change = $this->determineChange($changeAmount);

        if ($change === null) {
            return new ExactChangeUnavailable($product);
        }

        $this->catalog = $this->catalog->upsertEntry($entry->decrementStock());
        $this->availableChange = $this->availableChange
            ->remove($change)
            ->merge($this->insertedCoins);
        $this->insertedCoins = Coins::empty();

        return new ProductVended($product, $change);
    }

    private function determineChange(Money $changeAmount): ?Coins
    {
        if ($changeAmount->equals(Money::zero())) {
            return Coins::empty();
        }

        $plannedCoins = $this->determineChangeCoins(
            $this->availableChange->sortedDescending()->all(),
            $changeAmount->cents(),
            0,
        );

        return $plannedCoins === null ? null : new Coins($plannedCoins);
    }

    /**
     * @param list<Coin> $coins
     * @return list<Coin>|null
     */
    private function determineChangeCoins(array $coins, int $remaining, int $index): ?array
    {
        if ($remaining === 0) {
            return [];
        }

        if ($index >= count($coins)) {
            return null;
        }

        $coin = $coins[$index];

        if ($coin->cents() <= $remaining) {
            $coinsWithCurrent = $this->determineChangeCoins($coins, $remaining - $coin->cents(), $index + 1);

            if ($coinsWithCurrent !== null) {
                return [$coin, ...$coinsWithCurrent];
            }
        }

        return $this->determineChangeCoins($coins, $remaining, $index + 1);
    }
}
