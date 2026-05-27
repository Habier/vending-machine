<?php

declare(strict_types=1);

namespace VendingMachine\Cli;

use VendingMachine\Domain\Catalog;
use VendingMachine\Domain\CatalogEntry;

final readonly class ProductCommandResolver
{
    public function displaySelection(CatalogEntry $entry): string
    {
        return sprintf('GET-%s', $this->normalizeProductToken($entry));
    }

    public function selectionCodeFromGetCommand(string $command, Catalog $catalog): ?string
    {
        $requestedProduct = substr($command, 4);

        if ($requestedProduct === '') {
            return null;
        }

        foreach ($catalog->entries() as $entry) {
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
