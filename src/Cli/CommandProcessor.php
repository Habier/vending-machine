<?php

declare(strict_types=1);

namespace VendingMachine\Cli;

use InvalidArgumentException;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\ProductSelection;
use VendingMachine\Domain\VendingMachine;

final class CommandProcessor
{
    private bool $shouldExit = false;

    private readonly ServiceSession $serviceSession;

    private readonly CliOutputFormatter $formatter;

    private readonly ProductCommandResolver $productCommandResolver;

    public function __construct(private VendingMachine $machine)
    {
        $this->productCommandResolver = new ProductCommandResolver();
        $this->formatter = new CliOutputFormatter($this->productCommandResolver);
        $this->serviceSession = new ServiceSession(new ServiceInputParser(), $this->formatter);
    }

    public function help(): string
    {
        return $this->formatter->help($this->coinCommands());
    }

    public function shouldExit(): bool
    {
        return $this->shouldExit;
    }

    public function handle(string $input): string
    {
        $trimmedInput = trim($input);

        if ($this->serviceSession->isActive()) {
            return $this->serviceSession->handle($trimmedInput, $this->machine);
        }

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
            'STATUS' => $this->formatter->status($this->machine),
            'SERVICE' => $this->serviceSession->start(),
            'RETURN-COIN' => $this->returnCoins(),
            'EXIT' => $this->exit(),
            default => sprintf('Unknown command "%s". Type "HELP" for usage.', $rawCommand),
        };
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
            return $this->formatter->acceptedCoin($displayValue, $this->machine->insertedMoney()->cents());
        }

        return $this->formatter->rejectedCoin($displayValue);
    }

    private function selectByCode(string $code): string
    {
        try {
            $selection = new ProductSelection($code);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        $result = $this->machine->selectProduct($selection);

        return $this->formatter->selectionOutcome($result);
    }

    private function selectFromGetCommand(string $command): string
    {
        $selectionCode = $this->productCommandResolver->selectionCodeFromGetCommand($command, $this->machine->catalog());

        if ($selectionCode === null) {
            return sprintf('Unknown product command "%s".', $command);
        }

        return $this->selectByCode($selectionCode);
    }

    private function returnCoins(): string
    {
        return $this->formatter->returnedCoins($this->machine->returnCoins());
    }

    private function exit(): string
    {
        $this->shouldExit = true;

        return 'Bye.';
    }


    private function formatCoinCommand(int $cents): string
    {
        if ($cents % 100 === 0) {
            return (string) intdiv($cents, 100);
        }

        return number_format($cents / 100, 2, '.', '');
    }
}
