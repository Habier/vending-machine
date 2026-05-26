<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

use InvalidArgumentException;

final readonly class Catalog
{
    /**
     * @var array<string, CatalogEntry>
     */
    private array $entries;

    /**
     * @param list<CatalogEntry> $entries
     */
    public function __construct(array $entries)
    {
        $mappedEntries = [];

        foreach ($entries as $entry) {
            $code = $entry->product()->selection()->code();

            if (isset($mappedEntries[$code])) {
                throw new InvalidArgumentException(sprintf('Duplicate product selection "%s".', $code));
            }

            $mappedEntries[$code] = $entry;
        }

        $this->entries = $mappedEntries;
    }

    public function find(ProductSelection $selection): ?CatalogEntry
    {
        return $this->entries[$selection->code()] ?? null;
    }

    public function upsertEntry(CatalogEntry $entry): self
    {
        $entries = $this->entries;
        $entries[$entry->product()->selection()->code()] = $entry;

        return new self(array_values($entries));
    }
}
