<?php

declare(strict_types=1);

namespace VendingMachine\Cli;

use InvalidArgumentException;
use VendingMachine\Domain\Catalog;
use VendingMachine\Domain\CatalogEntry;
use VendingMachine\Domain\ChangeReserve;
use VendingMachine\Domain\VendingMachine;

final class ServiceSession
{
    private ?ChangeReserve $pendingReserve = null;

    /**
     * @var list<CatalogEntry>
     */
    private array $pendingEntries = [];

    /**
     * @var array<string, true>
     */
    private array $pendingNormalizedProductNames = [];

    private bool $awaitingReserve = false;

    private bool $awaitingProducts = false;

    public function __construct(
        private readonly ServiceInputParser $parser,
        private readonly CliOutputFormatter $formatter,
    ) {
    }

    public function start(): string
    {
        $this->pendingReserve = null;
        $this->pendingEntries = [];
        $this->pendingNormalizedProductNames = [];
        $this->awaitingReserve = true;
        $this->awaitingProducts = false;

        return $this->formatter->serviceStarted();
    }

    public function isActive(): bool
    {
        return $this->awaitingReserve || $this->awaitingProducts;
    }

    public function handle(string $input, VendingMachine $machine): string
    {
        if ($this->awaitingReserve) {
            return $this->handleReserveInput($input);
        }

        if (!$this->awaitingProducts) {
            return 'SERVICE mode is not active.';
        }

        return $this->handleProductInput($input, $machine);
    }

    private function handleReserveInput(string $input): string
    {
        if ($input === '') {
            return 'Enter the change reserve using denomination:quantity pairs.';
        }

        try {
            $this->pendingReserve = $this->parser->parseChangeReserve($input);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        $this->awaitingReserve = false;
        $this->awaitingProducts = true;

        return $this->formatter->serviceReserveSaved();
    }

    private function handleProductInput(string $input, VendingMachine $machine): string
    {
        if ($input === '') {
            return 'Enter a product as name|price|stock, or DONE to finish.';
        }

        if (strtoupper($input) === 'DONE') {
            $machine->service(
                new Catalog($this->pendingEntries),
                $this->pendingReserve ?? ChangeReserve::empty(),
            );

            $productCount = count($this->pendingEntries);
            $reserveTotal = ($this->pendingReserve ?? ChangeReserve::empty())->total()->cents();
            $this->reset();

            return $this->formatter->serviceCompleted($productCount, $reserveTotal);
        }

        try {
            $entry = $this->parsePendingProduct($input);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        $this->pendingEntries[] = $entry;

        return $this->formatter->serviceProductAdded($entry);
    }

    private function parsePendingProduct(string $input): CatalogEntry
    {
        $entry = $this->parser->parseProductEntry($input, count($this->pendingEntries) + 1);
        $normalizedName = $this->parser->normalizeProductName($entry->product()->name());

        if (isset($this->pendingNormalizedProductNames[$normalizedName])) {
            throw new InvalidArgumentException(sprintf('Duplicate product name "%s".', $entry->product()->name()));
        }

        $this->pendingNormalizedProductNames[$normalizedName] = true;

        return $entry;
    }

    private function reset(): void
    {
        $this->pendingReserve = null;
        $this->pendingEntries = [];
        $this->pendingNormalizedProductNames = [];
        $this->awaitingReserve = false;
        $this->awaitingProducts = false;
    }
}
