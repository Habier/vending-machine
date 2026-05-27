<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Cli;

use PHPUnit\Framework\TestCase;
use VendingMachine\Cli\CommandProcessor;
use VendingMachine\Domain\Catalog;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\ChangeReserve;
use VendingMachine\Domain\Money;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\ProductSelection;
use VendingMachine\Domain\VendingMachine;

final class CommandProcessorTest extends TestCase
{
    public function testHelpListsAvailableCommands(): void
    {
        $processor = $this->createProcessor();

        $help = $processor->handle('HELP');

        self::assertStringContainsString('Commands:', $help);
        self::assertStringContainsString('0.05', $help);
        self::assertStringContainsString('0.10', $help);
        self::assertStringContainsString('0.25', $help);
        self::assertStringContainsString('1', $help);
        self::assertStringContainsString('GET-<PRODUCT>', $help);
        self::assertStringContainsString('GET-WATER', $help);
        self::assertStringContainsString('Reconfigure change reserve and products interactively', $help);
        self::assertStringContainsString('RETURN-COIN', $help);
        self::assertStringContainsString('EXIT', $help);
    }

    public function testUnknownCommandReturnsHelpfulMessage(): void
    {
        $processor = $this->createProcessor();

        $result = $processor->handle('dance');

        self::assertSame('Unknown command "dance". Type "HELP" for usage.', $result);
    }

    public function testLegacyDeveloperStyleCommandsAreRejected(): void
    {
        $processor = $this->createProcessor();

        self::assertSame('Unknown command "insert". Type "HELP" for usage.', $processor->handle('insert nope'));
        self::assertSame('Unknown command "select". Type "HELP" for usage.', $processor->handle('select A1'));
        self::assertSame('Unknown command "return-coins". Type "HELP" for usage.', $processor->handle('return-coins'));
    }

    public function testExitMarksProcessorForShutdown(): void
    {
        $processor = $this->createProcessor();

        $result = $processor->handle('EXIT');

        self::assertSame('Bye.', $result);
        self::assertTrue($processor->shouldExit());
    }

    public function testSelectHappyPathDispensesProductAndReportsChange(): void
    {
        $processor = $this->createProcessor();

        self::assertSame('Accepted 1. Inserted total: 100c.', $processor->handle('1'));

        $result = $processor->handle('GET-WATER');

        self::assertSame('Dispensed Water. Change: 25c, 10c.', $result);
    }

    public function testDynamicGetCommandResolvesProductFromCatalogName(): void
    {
        $processor = $this->createProcessor($this->machineWith([
            $this->catalogEntry('A1', 'Sparkling Water', 100, 2),
        ]));

        self::assertSame('Accepted 1. Inserted total: 100c.', $processor->handle('1'));

        $result = $processor->handle('GET-SPARKLING-WATER');

        self::assertSame('Dispensed Sparkling Water. Change: none.', $result);
    }

    public function testUnknownDynamicGetCommandReturnsClearMessage(): void
    {
        $processor = $this->createProcessor();

        $result = $processor->handle('GET-TEA');

        self::assertSame('Unknown product command "GET-TEA".', $result);
    }

    public function testStatusDisplaysDynamicGetCommandsFromProductNames(): void
    {
        $processor = $this->createProcessor($this->machineWith([
            $this->catalogEntry('A1', 'Sparkling Water', 100, 2),
        ]));

        $status = $processor->handle('STATUS');

        self::assertStringContainsString('GET-SPARKLING-WATER  Sparkling Water  100c  stock=2', $status);
    }

    public function testChallengeCoinAliasesInsertAcceptedMoney(): void
    {
        $processor = $this->createProcessor();

        self::assertSame('Accepted 0.25. Inserted total: 25c.', $processor->handle('0.25'));
        self::assertSame('Accepted 0.10. Inserted total: 35c.', $processor->handle('0.10'));
        self::assertSame('Accepted 0.05. Inserted total: 40c.', $processor->handle('0.05'));
    }

    public function testServiceConfiguresReserveAndProductsWhilePreservingInsertedMoneyInStatus(): void
    {
        $processor = $this->createProcessor();

        $processor->handle('1');
        self::assertStringContainsString('SERVICE mode.', $processor->handle('SERVICE'));
        self::assertStringContainsString('Change reserve saved.', $processor->handle('1:2,0.25:1,0.10:3,0.05:4'));
        self::assertSame('Added Tea at 75c with stock 6. Enter another product or DONE.', $processor->handle('Tea|0.75|6'));
        self::assertSame('Added Chips at 125c with stock 2. Enter another product or DONE.', $processor->handle('Chips|1.25|2'));
        self::assertSame('Machine serviced. Loaded 2 products. Change reserve total: 275c.', $processor->handle('DONE'));

        $status = $processor->handle('STATUS');

        self::assertStringContainsString('Inserted: 100c', $status);
        self::assertStringContainsString('GET-TEA  Tea  75c  stock=6', $status);
        self::assertStringContainsString('GET-CHIPS  Chips  125c  stock=2', $status);
        self::assertStringContainsString('Change reserve: 100c, 100c, 25c, 10c, 10c, 10c, 5c, 5c, 5c, 5c (total 275c)', $status);
    }

    public function testServiceRejectsDuplicateProductNamesAfterTrimAndCaseNormalization(): void
    {
        $processor = $this->createProcessor();

        $processor->handle('SERVICE');
        $processor->handle('1:1,0.25:1,0.10:1,0.05:1');

        self::assertSame('Added Water at 65c with stock 5. Enter another product or DONE.', $processor->handle('Water|0.65|5'));
        self::assertSame('Duplicate product name "water".', $processor->handle(' water |1.00|1'));
    }

    public function testServiceRejectsUnsupportedReserveDenominations(): void
    {
        $processor = $this->createProcessor();

        $processor->handle('SERVICE');

        self::assertSame('Unsupported reserve denomination "0.50".', $processor->handle('0.50:2'));
    }

    public function testServiceRejectsMalformedReserveInput(): void
    {
        $processor = $this->createProcessor();

        $processor->handle('SERVICE');

        self::assertSame(
            'Invalid reserve pair "1". Use denomination:quantity.',
            $processor->handle('1'),
        );
    }

    public function testServiceRejectsMalformedProductLine(): void
    {
        $processor = $this->createProcessor();

        $processor->handle('SERVICE');
        $processor->handle('1:1,0.25:1,0.10:1,0.05:1');

        self::assertSame(
            'Invalid product "Water|0.65". Use name|price|stock.',
            $processor->handle('Water|0.65'),
        );
    }

    public function testReturnCoinUsesChallengeCommandName(): void
    {
        $processor = $this->createProcessor();

        $processor->handle('0.25');
        $processor->handle('0.10');

        self::assertSame('Returned coins: 25c, 10c.', $processor->handle('RETURN-COIN'));
    }

    private function createProcessor(?VendingMachine $machine = null): CommandProcessor
    {
        return new CommandProcessor($machine ?? $this->createDefaultMachine());
    }

    private function createDefaultMachine(): VendingMachine
    {
        return $this->machineWith([
            $this->catalogEntry('WATER', 'Water', 65, 5),
            $this->catalogEntry('JUICE', 'Juice', 100, 5),
            $this->catalogEntry('SODA', 'Soda', 150, 5),
        ]);
    }

    /**
     * @param list<CatalogEntry> $entries
     */
    private function machineWith(array $entries): VendingMachine
    {
        return new VendingMachine(new Catalog($entries), ChangeReserve::fromCents(100, 25, 25, 10, 10, 5));
    }

    private function catalogEntry(string $selection, string $name, int $priceInCents, int $stock): CatalogEntry
    {
        return new CatalogEntry(
            new Product(new ProductSelection($selection), $name, new Money($priceInCents)),
            $stock,
        );
    }
}
