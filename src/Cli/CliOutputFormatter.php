<?php

declare(strict_types=1);

namespace VendingMachine\Cli;

use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\Coins;
use VendingMachine\Domain\Result\ExactChangeUnavailable;
use VendingMachine\Domain\Result\InsufficientFunds;
use VendingMachine\Domain\Result\OutOfStock;
use VendingMachine\Domain\Result\ProductSelectionResult;
use VendingMachine\Domain\Result\ProductVended;
use VendingMachine\Domain\Result\UnknownProductSelection;
use VendingMachine\Domain\VendingMachine;

final readonly class CliOutputFormatter
{
    public function __construct(private ProductCommandResolver $productCommandResolver)
    {
    }

    /**
     * @param array<string, int> $coinCommands
     */
    public function help(array $coinCommands): string
    {
        $lines = ['Commands:'];

        foreach ($coinCommands as $command => $cents) {
            $lines[] = sprintf('  %-16s Insert %d cents', $command, $cents);
        }

        $lines = [...$lines,
            '  GET-<PRODUCT>    Buy a catalog product, for example GET-WATER',
            '  RETURN-COIN      Return the exact inserted coins',
            '  SERVICE          Reconfigure change reserve and products interactively',
            '  STATUS           Show inserted money, products, and change reserve',
            '  HELP             Show this help',
            '  EXIT             Exit the CLI',
        ];

        return implode(PHP_EOL, $lines);
    }

    public function acceptedCoin(string $displayValue, int $insertedTotal): string
    {
        return sprintf('Accepted %s. Inserted total: %dc.', $displayValue, $insertedTotal);
    }

    public function rejectedCoin(string $displayValue): string
    {
        return sprintf('Rejected %s. Returned immediately.', $displayValue);
    }

    public function selectionOutcome(ProductSelectionResult $result): string
    {
        return match (true) {
            $result instanceof ProductVended => sprintf(
                'Dispensed %s. Change: %s.',
                $result->product->name(),
                $this->formatCoins($result->change),
            ),
            $result instanceof InsufficientFunds => sprintf(
                'Cannot dispense %s. Missing %dc.',
                $result->product->name(),
                $result->missingAmount->cents(),
            ),
            $result instanceof OutOfStock => sprintf('Cannot dispense %s. Product is out of stock.', $result->product->name()),
            $result instanceof ExactChangeUnavailable => sprintf(
                'Cannot dispense %s. Exact change is unavailable.',
                $result->product->name(),
            ),
            $result instanceof UnknownProductSelection => sprintf(
                'Unknown product selection "%s".',
                $result->selection->code(),
            ),
            default => 'Unknown selection outcome.',
        };
    }

    public function returnedCoins(Coins $coins): string
    {
        return sprintf('Returned coins: %s.', $this->formatCoins($coins));
    }

    public function status(VendingMachine $machine): string
    {
        $lines = [
            sprintf('Inserted: %dc', $machine->insertedMoney()->cents()),
            'Products:',
        ];

        foreach ($machine->catalog()->entries() as $entry) {
            $product = $entry->product();
            $lines[] = sprintf(
                '  %s  %s  %dc  stock=%d',
                $this->productCommandResolver->displaySelection($entry),
                $product->name(),
                $product->price()->cents(),
                $entry->stock(),
            );
        }

        $lines[] = sprintf(
            'Change reserve: %s (total %dc)',
            $this->formatCoins($machine->availableChange()->coins()),
            $machine->availableChange()->total()->cents(),
        );

        return implode(PHP_EOL, $lines);
    }

    public function serviceStarted(): string
    {
        return implode(PHP_EOL, [
            'SERVICE mode.',
            'Enter change reserve as denomination:quantity pairs, comma-separated.',
            'Example: 1:33,0.25:66,0.10:22,0.05:44',
            'Change reserve:',
        ]);
    }

    public function serviceReserveSaved(): string
    {
        return implode(PHP_EOL, [
            'Change reserve saved.',
            'Enter products one per line as name|price|stock.',
            'Example: Water|0.65|5',
            'Type DONE when finished.',
        ]);
    }

    public function serviceProductAdded(CatalogEntry $entry): string
    {
        return sprintf(
            'Added %s at %dc with stock %d. Enter another product or DONE.',
            $entry->product()->name(),
            $entry->product()->price()->cents(),
            $entry->stock(),
        );
    }

    public function serviceCompleted(int $productCount, int $reserveTotal): string
    {
        return sprintf(
            'Machine serviced. Loaded %d product%s. Change reserve total: %dc.',
            $productCount,
            $productCount === 1 ? '' : 's',
            $reserveTotal,
        );
    }

    private function formatCoins(Coins $coins): string
    {
        if ($coins->isEmpty()) {
            return 'none';
        }

        return implode(', ', array_map(static fn (int $cents): string => sprintf('%dc', $cents), $coins->values()));
    }
}
