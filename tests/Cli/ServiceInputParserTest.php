<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Cli;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VendingMachine\Cli\ServiceInputParser;

final class ServiceInputParserTest extends TestCase
{
    private ServiceInputParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ServiceInputParser();
    }

    public function testParseChangeReserveBuildsReserveFromValidPairs(): void
    {
        $reserve = $this->parser->parseChangeReserve('1:2,0.25:1,0.10:3,0.05:4');

        self::assertSame([100, 100, 25, 10, 10, 10, 5, 5, 5, 5], $reserve->coins()->values());
        self::assertSame(275, $reserve->total()->cents());
    }

    public function testParseChangeReserveRejectsInvalidPair(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid reserve pair "1". Use denomination:quantity.');

        $this->parser->parseChangeReserve('1');
    }

    public function testParseChangeReserveRejectsDuplicateDenomination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate reserve denomination "0.25".');

        $this->parser->parseChangeReserve('0.25:1,0.25:2');
    }

    public function testParseProductEntryBuildsCatalogEntryFromValidLine(): void
    {
        $entry = $this->parser->parseProductEntry(' Sparkling Water | 1.25 | 6 ', 3);

        self::assertSame('P3', $entry->product()->selection()->code());
        self::assertSame('Sparkling Water', $entry->product()->name());
        self::assertSame(125, $entry->product()->price()->cents());
        self::assertSame(6, $entry->stock());
    }

    public function testParseProductEntryRejectsMalformedLine(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid product "Water|0.65". Use name|price|stock.');

        $this->parser->parseProductEntry('Water|0.65', 1);
    }

    public function testNormalizeProductNameMatchesDuplicateDetectionRules(): void
    {
        self::assertSame('water', $this->parser->normalizeProductName('Water'));
        self::assertSame('water', $this->parser->normalizeProductName(' water '));
        self::assertSame('sparkling water', $this->parser->normalizeProductName(' Sparkling Water '));
    }
}
