<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VendingMachine\Domain\Catalog;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\Coins;
use VendingMachine\Domain\Money;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\ProductSelection;

final class DomainInvariantTest extends TestCase
{
    public function testMoneyRejectsNegativeAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Money cannot be negative.');

        new Money(-1);
    }

    public function testMoneyRejectsSubtractingTooMuch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot subtract more money than available.');

        new Money(10)->subtract(new Money(15));
    }

    public function testCatalogRejectsDuplicateSelector(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate product selection "A1".');

        new Catalog([
            $this->createEntry('A1', 'Soda', 125, 5),
            $this->createEntry('A1', 'Diet Soda', 130, 3),
        ]);
    }

    public function testCatalogEntryRejectsNegativeStock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock cannot be negative.');

        new CatalogEntry($this->createProduct('A1', 'Soda', 125), -1);
    }

    public function testCatalogEntryRejectsDecrementWhenOutOfStock(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot decrement out-of-stock product.');

        $this->createEntry('A1', 'Soda', 125, 0)->decrementStock();
    }

    public function testCoinsRejectRemovingUnavailableCoins(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot remove coins that are not available.');

        Coins::fromCents(25, 10)->remove(Coins::fromCents(25, 25));
    }

    private function createEntry(string $code, string $name, int $price, int $stock): CatalogEntry
    {
        return new CatalogEntry($this->createProduct($code, $name, $price), $stock);
    }

    private function createProduct(string $code, string $name, int $price): Product
    {
        return new Product(new ProductSelection($code), $name, new Money($price));
    }
}
