<?php

declare(strict_types=1);

namespace VendingMachine\Cli;

use InvalidArgumentException;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\ChangeReserve;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\Coins;
use VendingMachine\Domain\Money;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\ProductSelection;
use VendingMachine\Domain\VendingMachine;

final readonly class ServiceInputParser
{
    public function parseChangeReserve(string $input): ChangeReserve
    {
        $pairs = array_filter(array_map('trim', explode(',', $input)), static fn (string $pair): bool => $pair !== '');

        if ($pairs === []) {
            throw new InvalidArgumentException('Change reserve cannot be empty. Example: 1:33,0.25:66,0.10:22,0.05:44');
        }

        $coins = [];
        $seenDenominations = [];

        foreach ($pairs as $pair) {
            $parts = array_map('trim', explode(':', $pair));

            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                throw new InvalidArgumentException(sprintf('Invalid reserve pair "%s". Use denomination:quantity.', $pair));
            }

            [$denomination, $quantity] = $parts;
            $denominationInCents = $this->parseMoneyInputToCents($denomination, 'reserve denomination');

            if (!in_array($denominationInCents, VendingMachine::acceptedCoinDenominations(), true)) {
                throw new InvalidArgumentException(sprintf('Unsupported reserve denomination "%s".', $denomination));
            }

            if (isset($seenDenominations[$denominationInCents])) {
                throw new InvalidArgumentException(sprintf('Duplicate reserve denomination "%s".', $denomination));
            }

            if (!ctype_digit($quantity)) {
                throw new InvalidArgumentException(sprintf('Invalid reserve quantity "%s".', $quantity));
            }

            $seenDenominations[$denominationInCents] = true;

            for ($index = 0, $count = (int) $quantity; $index < $count; $index++) {
                $coins[] = new Coin($denominationInCents);
            }
        }

        return ChangeReserve::fromCoins(new Coins($coins));
    }

    public function parseProductEntry(string $input, int $position): CatalogEntry
    {
        $parts = explode('|', $input);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException(sprintf('Invalid product "%s". Use name|price|stock.', $input));
        }

        [$rawName, $rawPrice, $rawStock] = array_map('trim', $parts);

        if ($this->normalizeProductName($rawName) === '') {
            throw new InvalidArgumentException('Product name cannot be empty.');
        }

        if (!ctype_digit($rawStock)) {
            throw new InvalidArgumentException(sprintf('Invalid stock "%s".', $rawStock));
        }

        return new CatalogEntry(
            new Product(
                new ProductSelection(sprintf('P%d', $position)),
                trim($rawName),
                new Money($this->parseMoneyInputToCents($rawPrice, 'product price')),
            ),
            (int) $rawStock,
        );
    }

    public function normalizeProductName(string $name): string
    {
        return strtolower(trim($name));
    }

    private function parseMoneyInputToCents(string $value, string $field): int
    {
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException(sprintf('Invalid %s "%s".', $field, $value));
        }

        $parts = explode('.', $value, 2);
        $whole = (int) $parts[0];
        $fraction = str_pad($parts[1] ?? '', 2, '0');

        return ($whole * 100) + (int) $fraction;
    }
}
