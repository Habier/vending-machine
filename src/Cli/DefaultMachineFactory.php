<?php

declare(strict_types=1);

namespace VendingMachine\Cli;

use VendingMachine\Domain\Catalog;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\ChangeReserve;
use VendingMachine\Domain\Money;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\ProductSelection;
use VendingMachine\Domain\VendingMachine;

final class DefaultMachineFactory
{
    public function create(): VendingMachine
    {
        return new VendingMachine(
            new Catalog([
                $this->catalogEntry('WATER', 'Water', 65, 5),
                $this->catalogEntry('JUICE', 'Juice', 100, 5),
                $this->catalogEntry('SODA', 'Soda', 150, 5),
            ]),
            ChangeReserve::fromCents(100, 25, 25, 10, 10, 5),
        );
    }

    private function catalogEntry(string $selection, string $name, int $priceInCents, int $stock): CatalogEntry
    {
        return new CatalogEntry(
            new Product(new ProductSelection($selection), $name, new Money($priceInCents)),
            $stock,
        );
    }
}
