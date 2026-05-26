<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Cli;

use PHPUnit\Framework\TestCase;
use VendingMachine\Cli\CommandProcessor;
use VendingMachine\Cli\DefaultMachineFactory;
use VendingMachine\Domain\Catalog;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\Coins;
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

    public function testServiceResetsCatalogAndReserveWhilePreservingInsertedMoneyInStatus(): void
    {
        $processor = $this->createProcessor();

        $processor->handle('1');
        $processor->handle('SERVICE');

        $status = $processor->handle('STATUS');

        self::assertStringContainsString('Inserted: 100c', $status);
        self::assertStringContainsString('GET-WATER  Water  65c  stock=5', $status);
        self::assertStringContainsString('GET-JUICE  Juice  100c  stock=5', $status);
        self::assertStringContainsString('GET-SODA  Soda  150c  stock=5', $status);
        self::assertStringContainsString('Change reserve: 100c, 25c, 25c, 10c, 10c, 5c (total 175c)', $status);
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
        $factory = new DefaultMachineFactory();

        return new CommandProcessor($machine ?? $factory->create(), $factory);
    }

    /**
     * @param list<CatalogEntry> $entries
     */
    private function machineWith(array $entries): VendingMachine
    {
        return new VendingMachine(new Catalog($entries), Coins::fromCents(100, 25, 25, 10, 10, 5));
    }

    private function catalogEntry(string $selection, string $name, int $priceInCents, int $stock): CatalogEntry
    {
        return new CatalogEntry(
            new Product(new ProductSelection($selection), $name, new Money($priceInCents)),
            $stock,
        );
    }
}
