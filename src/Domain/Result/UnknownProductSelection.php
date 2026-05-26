<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Result;

use VendingMachine\Domain\ProductSelection;

final readonly class UnknownProductSelection implements ProductSelectionResult
{
    public function __construct(public ProductSelection $selection)
    {
    }
}
