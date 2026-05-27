<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Cli;

use PHPUnit\Framework\TestCase;
use VendingMachine\Cli\ProductCommandResolver;
use VendingMachine\Domain\Catalog;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\Money;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\ProductSelection;

final class ProductCommandResolverTest extends TestCase
{
    private ProductCommandResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ProductCommandResolver();
    }

    public function testSelectionCodeFromGetCommandResolvesNormalizedCommand(): void
    {
        $catalog = new Catalog([
            $this->catalogEntry('A1', 'Sparkling Water', 100, 2),
        ]);

        $selectionCode = $this->resolver->selectionCodeFromGetCommand('GET-SPARKLING-WATER', $catalog);

        self::assertSame('A1', $selectionCode);
    }

    public function testSelectionCodeFromGetCommandReturnsNullForUnknownCommand(): void
    {
        $catalog = new Catalog([
            $this->catalogEntry('A1', 'Water', 65, 5),
        ]);

        $selectionCode = $this->resolver->selectionCodeFromGetCommand('GET-TEA', $catalog);

        self::assertNull($selectionCode);
    }

    public function testDisplaySelectionFormatsProductNameAsGetCommand(): void
    {
        $command = $this->resolver->displaySelection(
            $this->catalogEntry('A1', 'Sparkling_Water', 100, 2),
        );

        self::assertSame('GET-SPARKLING-WATER', $command);
    }

    private function catalogEntry(string $selection, string $name, int $priceInCents, int $stock): CatalogEntry
    {
        return new CatalogEntry(
            new Product(new ProductSelection($selection), $name, new Money($priceInCents)),
            $stock,
        );
    }
}
