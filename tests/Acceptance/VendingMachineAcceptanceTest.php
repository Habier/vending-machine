<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Acceptance;

use PHPUnit\Framework\TestCase;
use VendingMachine\Domain\Catalog;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\ChangeReserve;
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
        $machine->insertCoin(new Coin(25));

        $result = $machine->selectProduct(new ProductSelection('SODA'));

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

        $result = $machine->selectProduct(new ProductSelection('WATER'));

        self::assertInstanceOf(ProductVended::class, $result);
        self::assertSame('Water', $result->product->name());
        self::assertSame([25, 10], $result->change->values());
        self::assertSame(0, $machine->insertedMoney()->cents());
    }

    public function testInsufficientFundsReturnsFailureAndPreservesMoney(): void
    {
        $machine = $this->createMachine();
        $machine->insertCoin(new Coin(100));

        $result = $machine->selectProduct(new ProductSelection('SODA'));

        self::assertInstanceOf(InsufficientFunds::class, $result);
        self::assertSame(50, $result->missingAmount->cents());
        self::assertSame(100, $machine->insertedMoney()->cents());
        self::assertSame([100], $machine->returnCoins()->values());
    }

    public function testOutOfStockReturnsFailureAndPreservesMoney(): void
    {
        $machine = $this->machineWith(
            [
                $this->catalogEntry('SODA', 'Soda', 150, 0),
            ],
            ChangeReserve::fromCents(25, 10, 5),
        );
        $machine->insertCoin(new Coin(100));
        $machine->insertCoin(new Coin(25));
        $machine->insertCoin(new Coin(25));

        $result = $machine->selectProduct(new ProductSelection('SODA'));

        self::assertInstanceOf(OutOfStock::class, $result);
        self::assertSame('Soda', $result->product->name());
        self::assertSame(150, $machine->insertedMoney()->cents());
        self::assertSame([100, 25, 25], $machine->returnCoins()->values());
    }

    public function testExactChangeUnavailableReturnsFailureAndPreservesMoney(): void
    {
        $machine = $this->machineWith(
            [
                $this->catalogEntry('WATER', 'Water', 65, 5),
            ],
            ChangeReserve::fromCents(25, 5),
        );
        $machine->insertCoin(new Coin(100));

        $result = $machine->selectProduct(new ProductSelection('WATER'));

        self::assertInstanceOf(ExactChangeUnavailable::class, $result);
        self::assertSame('Water', $result->product->name());
        self::assertSame(100, $machine->insertedMoney()->cents());
        self::assertSame([100], $machine->returnCoins()->values());
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
                $this->catalogEntry('SPARKLING-WATER', 'Sparkling Water', 100, 3),
            ]),
            ChangeReserve::empty(),
        );

        self::assertSame(100, $machine->insertedMoney()->cents());
        self::assertInstanceOf(UnknownProductSelection::class, $machine->selectProduct(new ProductSelection('WATER')));

        $machine->insertCoin(new Coin(25));

        $result = $machine->selectProduct(new ProductSelection('SPARKLING-WATER'));

        self::assertInstanceOf(ExactChangeUnavailable::class, $result);
        self::assertSame('Sparkling Water', $result->product->name());
        self::assertSame(125, $machine->insertedMoney()->cents());
        self::assertSame([100, 25], $machine->returnCoins()->values());
    }

    private function createMachine(): VendingMachine
    {
        return $this->machineWith(
            [
                $this->catalogEntry('WATER', 'Water', 65, 5),
                $this->catalogEntry('JUICE', 'Juice', 100, 5),
                $this->catalogEntry('SODA', 'Soda', 150, 5),
            ],
            ChangeReserve::fromCents(100, 25, 25, 10, 10, 5),
        );
    }

    /**
     * @param list<CatalogEntry> $entries
     */
    private function machineWith(array $entries, ChangeReserve $availableChange): VendingMachine
    {
        return new VendingMachine(new Catalog($entries), $availableChange);
    }

    private function catalogEntry(string $selection, string $name, int $priceInCents, int $stock): CatalogEntry
    {
        return new CatalogEntry(
            new Product(new ProductSelection($selection), $name, new Money($priceInCents)),
            $stock,
        );
    }
}
