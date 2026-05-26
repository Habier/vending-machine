<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Acceptance;

use PHPUnit\Framework\TestCase;
use VendingMachine\Domain\Catalog;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\Coins;
use VendingMachine\Domain\Money;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\ProductSelection;
use VendingMachine\Domain\Result\ExactChangeUnavailable;
use VendingMachine\Domain\Result\InsufficientFunds;
use VendingMachine\Domain\Result\OutOfStock;
use VendingMachine\Domain\Result\ProductVended;
use VendingMachine\Domain\Result\UnknownProductSelection;
use VendingMachine\Domain\VendingMachine;

final class VendingMachineAcceptanceTest extends TestCase
{
    public function testBuySodaWithExactChangeVendsSoda(): void
    {
        $machine = $this->createMachine();
        $machine->insertCoin(new Coin(100));
        $machine->insertCoin(new Coin(25));

        $result = $machine->selectProduct(new ProductSelection('A1'));

        self::assertInstanceOf(ProductVended::class, $result);
        self::assertSame('Soda', $result->product->name());
        self::assertSame([], $result->change->values());
        self::assertSame(0, $machine->insertedMoney()->cents());
    }

    public function testReturnCoinReturnsExactInsertedCoins(): void
    {
        $machine = $this->createMachine();
        $machine->insertCoin(new Coin(25));
        $machine->insertCoin(new Coin(10));
        $machine->insertCoin(new Coin(25));

        $returnedCoins = $machine->returnCoins();

        self::assertSame([25, 10, 25], $returnedCoins->values());
        self::assertSame(0, $machine->insertedMoney()->cents());
    }

    public function testBuyWaterWithExtraMoneyReturnsDescendingChange(): void
    {
        $machine = $this->createMachine();
        $machine->insertCoin(new Coin(100));
        $machine->insertCoin(new Coin(100));

        $result = $machine->selectProduct(new ProductSelection('B1'));

        self::assertInstanceOf(ProductVended::class, $result);
        self::assertSame('Water', $result->product->name());
        self::assertSame([25, 10], $result->change->values());
        self::assertSame(0, $machine->insertedMoney()->cents());
    }

    public function testInsufficientFundsReturnsFailureAndPreservesMoney(): void
    {
        $machine = $this->createMachine();
        $machine->insertCoin(new Coin(100));

        $result = $machine->selectProduct(new ProductSelection('A1'));

        self::assertInstanceOf(InsufficientFunds::class, $result);
        self::assertSame(25, $result->missingAmount->cents());
        self::assertSame(100, $machine->insertedMoney()->cents());
        self::assertSame([100], $machine->returnCoins()->values());
    }

    public function testOutOfStockReturnsFailureAndPreservesMoney(): void
    {
        $machine = new VendingMachine(
            new Catalog([
                new CatalogEntry(
                    new Product(new ProductSelection('A1'), 'Soda', new Money(125)),
                    0,
                ),
            ]),
            Coins::fromCents(25, 10, 5),
        );
        $machine->insertCoin(new Coin(100));
        $machine->insertCoin(new Coin(25));

        $result = $machine->selectProduct(new ProductSelection('A1'));

        self::assertInstanceOf(OutOfStock::class, $result);
        self::assertSame('Soda', $result->product->name());
        self::assertSame(125, $machine->insertedMoney()->cents());
        self::assertSame([100, 25], $machine->returnCoins()->values());
    }

    public function testExactChangeUnavailableReturnsFailureAndPreservesMoney(): void
    {
        $machine = new VendingMachine(
            new Catalog([
                new CatalogEntry(
                    new Product(new ProductSelection('A1'), 'Soda', new Money(120)),
                    5,
                ),
            ]),
            Coins::fromCents(10),
        );
        $machine->insertCoin(new Coin(100));
        $machine->insertCoin(new Coin(25));

        $result = $machine->selectProduct(new ProductSelection('A1'));

        self::assertInstanceOf(ExactChangeUnavailable::class, $result);
        self::assertSame('Soda', $result->product->name());
        self::assertSame(125, $machine->insertedMoney()->cents());
        self::assertSame([100, 25], $machine->returnCoins()->values());
    }

    public function testUnknownProductSelectionReturnsExpectedResult(): void
    {
        $machine = $this->createMachine();

        $result = $machine->selectProduct(new ProductSelection('Z9'));

        self::assertInstanceOf(UnknownProductSelection::class, $result);
        self::assertSame('Z9', $result->selection->code());
    }

    public function testInvalidCoinIsRejectedAndReturnedImmediately(): void
    {
        $machine = $this->createMachine();

        $result = $machine->insertCoin(new Coin(50));

        self::assertFalse($result->accepted);
        self::assertSame(50, $result->returnedCoin?->cents());
        self::assertSame(0, $machine->insertedMoney()->cents());
    }

    public function testServiceReplacesCatalogAndChangeWhilePreservingInsertedMoney(): void
    {
        $machine = $this->createMachine();
        $machine->insertCoin(new Coin(100));

        $machine->service(
            new Catalog([
                new CatalogEntry(
                    new Product(new ProductSelection('A1'), 'Sparkling Water', new Money(100)),
                    3,
                ),
            ]),
            Coins::empty(),
        );

        self::assertSame(100, $machine->insertedMoney()->cents());
        self::assertInstanceOf(UnknownProductSelection::class, $machine->selectProduct(new ProductSelection('B1')));

        $machine->insertCoin(new Coin(25));

        $result = $machine->selectProduct(new ProductSelection('A1'));

        self::assertInstanceOf(ExactChangeUnavailable::class, $result);
        self::assertSame('Sparkling Water', $result->product->name());
        self::assertSame(125, $machine->insertedMoney()->cents());
        self::assertSame([100, 25], $machine->returnCoins()->values());
    }

    private function createMachine(): VendingMachine
    {
        return new VendingMachine(
            new Catalog([
                new CatalogEntry(
                    new Product(new ProductSelection('A1'), 'Soda', new Money(125)),
                    5,
                ),
                new CatalogEntry(
                    new Product(new ProductSelection('B1'), 'Water', new Money(165)),
                    5,
                ),
            ]),
            Coins::fromCents(100, 50, 25, 25, 10, 10, 5),
        );
    }
}
