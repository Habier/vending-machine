<?php

declare(strict_types=1);

namespace VendingMachine\Cli;

use InvalidArgumentException;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\Coins;
use VendingMachine\Domain\ProductSelection;
use VendingMachine\Domain\Result\ExactChangeUnavailable;
use VendingMachine\Domain\Result\InsufficientFunds;
use VendingMachine\Domain\Result\OutOfStock;
use VendingMachine\Domain\Result\ProductVended;
use VendingMachine\Domain\Result\UnknownProductSelection;
use VendingMachine\Domain\VendingMachine;

final class CommandProcessor
{
    private bool $shouldExit = false;

    public function __construct(
        private VendingMachine $machine,
        private readonly DefaultMachineFactory $defaultMachineFactory,
    ) {
    }

    public function help(): string
    {
        $lines = ['Commands:'];

        foreach ($this->coinCommands() as $command => $cents) {
            $lines[] = sprintf('  %-16s Insert %d cents', $command, $cents);
        }

        $lines = [...$lines,
            '  GET-<PRODUCT>    Buy a catalog product, for example GET-WATER',
            '  RETURN-COIN      Return the exact inserted coins',
            '  SERVICE          Reset catalog and change reserve to the challenge setup',
            '  STATUS           Show inserted money, products, and change reserve',
            '  HELP             Show this help',
            '  EXIT             Exit the CLI',
        ];

        return implode(PHP_EOL, $lines);
    }

    public function shouldExit(): bool
    {
        return $this->shouldExit;
    }

    public function handle(string $input): string
    {
        $trimmedInput = trim($input);

        if ($trimmedInput === '') {
            return 'Enter a command. Type "HELP" for usage.';
        }

        $coinCommands = $this->coinCommands();

        if (isset($coinCommands[$trimmedInput])) {
            return $this->insertCoinValue($coinCommands[$trimmedInput], $trimmedInput);
        }

        $parts = preg_split('/\s+/', $trimmedInput, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return 'Enter a command. Type "HELP" for usage.';
        }

        $rawCommand = $parts[0];
        $command = strtoupper($rawCommand);

        if (str_starts_with($command, 'GET-')) {
            return $this->selectFromGetCommand($command);
        }

        return match ($command) {
            'HELP' => $this->help(),
            'STATUS' => $this->renderStatus(),
            'SERVICE' => $this->service(),
            'RETURN-COIN' => $this->returnCoins(),
            'EXIT' => $this->exit(),
            default => sprintf('Unknown command "%s". Type "HELP" for usage.', $rawCommand),
        };
    }

    private function service(): string
    {
        $defaultMachine = $this->defaultMachineFactory->create();
        $this->machine->service($defaultMachine->catalog(), $defaultMachine->availableChange());

        return 'Machine serviced with the challenge configuration.';
    }

    /**
     * @return array<string, int>
     */
    private function coinCommands(): array
    {
        $commands = [];

        foreach (VendingMachine::acceptedCoinDenominations() as $cents) {
            $commands[$this->formatCoinCommand($cents)] = $cents;
        }

        return $commands;
    }

    private function insertCoinValue(int $cents, string $displayValue): string
    {
        try {
            $coin = new Coin($cents);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        $result = $this->machine->insertCoin($coin);

        if ($result->accepted) {
            return sprintf(
                'Accepted %s. Inserted total: %dc.',
                $displayValue,
                $this->machine->insertedMoney()->cents(),
            );
        }

        return sprintf('Rejected %s. Returned immediately.', $displayValue);
    }

    private function selectByCode(string $code): string
    {
        try {
            $selection = new ProductSelection($code);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        $result = $this->machine->selectProduct($selection);

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

    private function selectFromGetCommand(string $command): string
    {
        $selectionCode = $this->selectionCodeFromGetCommand($command);

        if ($selectionCode === null) {
            return sprintf('Unknown product command "%s".', $command);
        }

        return $this->selectByCode($selectionCode);
    }

    private function returnCoins(): string
    {
        $returnedCoins = $this->machine->returnCoins();

        return sprintf('Returned coins: %s.', $this->formatCoins($returnedCoins));
    }

    private function exit(): string
    {
        $this->shouldExit = true;

        return 'Bye.';
    }

    private function renderStatus(): string
    {
        $lines = [
            sprintf('Inserted: %dc', $this->machine->insertedMoney()->cents()),
            'Products:',
        ];

        foreach ($this->machine->catalog()->entries() as $entry) {
            $product = $entry->product();
            $lines[] = sprintf(
                '  %s  %s  %dc  stock=%d',
                $this->displaySelection($entry),
                $product->name(),
                $product->price()->cents(),
                $entry->stock(),
            );
        }

        $lines[] = sprintf(
            'Change reserve: %s (total %dc)',
            $this->formatCoins($this->machine->availableChange()->coins()),
            $this->machine->availableChange()->total()->cents(),
        );

        return implode(PHP_EOL, $lines);
    }

    private function formatCoinCommand(int $cents): string
    {
        if ($cents % 100 === 0) {
            return (string) intdiv($cents, 100);
        }

        return number_format($cents / 100, 2, '.', '');
    }

    private function formatCoins(Coins $coins): string
    {
        if ($coins->isEmpty()) {
            return 'none';
        }

        return implode(', ', array_map(static fn (int $cents): string => sprintf('%dc', $cents), $coins->values()));
    }

    private function displaySelection(CatalogEntry $entry): string
    {
        return sprintf('GET-%s', $this->normalizeProductToken($entry));
    }

    private function selectionCodeFromGetCommand(string $command): ?string
    {
        $requestedProduct = substr($command, 4);

        if ($requestedProduct === '') {
            return null;
        }

        foreach ($this->machine->catalog()->entries() as $entry) {
            if ($this->normalizeProductToken($entry) === $requestedProduct) {
                return $entry->product()->selection()->code();
            }
        }

        return null;
    }

    private function normalizeProductToken(CatalogEntry $entry): string
    {
        return strtoupper(str_replace([' ', '_'], '-', $entry->product()->name()));
    }
}
