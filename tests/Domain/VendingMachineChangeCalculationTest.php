<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Domain;

use PHPUnit\Framework\TestCase;
use VendingMachine\Domain\Catalog;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\Coins;
use VendingMachine\Domain\Money;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\ProductSelection;
use VendingMachine\Domain\Result\ExactChangeUnavailable;
use VendingMachine\Domain\Result\ProductVended;
use VendingMachine\Domain\VendingMachine;

final class VendingMachineChangeCalculationTest extends TestCase
{
    public function testSelectProductFindsExistingChangeCombination(): void
    {
        $machine = $this->createMachineWithPriceAndChange(160, 25, 10, 5);
        $machine->insertCoin(new Coin(100));
        $machine->insertCoin(new Coin(100));

        $result = $machine->selectProduct(new ProductSelection('A1'));

        self::assertInstanceOf(ProductVended::class, $result);
        self::assertSame([25, 10, 5], $result->change->values());
        self::assertSame(0, $machine->insertedMoney()->cents());
    }

    public function testSelectProductBacktracksWhenFirstLargerCoinPathFails(): void
    {
        $machine = $this->createMachineWithPriceAndChange(70, 25, 10, 10, 10);
        $machine->insertCoin(new Coin(100));

        $result = $machine->selectProduct(new ProductSelection('A1'));

        self::assertInstanceOf(ProductVended::class, $result);
        self::assertSame([10, 10, 10], $result->change->values());
        self::assertSame(0, $machine->insertedMoney()->cents());
    }

    public function testSelectProductReturnsFailureWhenNoExactChangeCombinationExists(): void
    {
        $machine = $this->createMachineWithPriceAndChange(70, 25, 10);
        $machine->insertCoin(new Coin(100));

        $result = $machine->selectProduct(new ProductSelection('A1'));

        self::assertInstanceOf(ExactChangeUnavailable::class, $result);
        self::assertSame(100, $machine->insertedMoney()->cents());
    }

    private function createMachineWithPriceAndChange(int $price, int ...$change): VendingMachine
    {
        return new VendingMachine(
            new Catalog([
                new CatalogEntry(
                    new Product(new ProductSelection('A1'), 'Soda', new Money($price)),
                    5,
                ),
            ]),
            Coins::fromCents(...$change),
        );
    }
}
