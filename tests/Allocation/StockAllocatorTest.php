<?php

namespace App\Tests\Allocation;

use App\Allocation\StockAllocator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StockAllocatorTest extends TestCase
{
    private StockAllocator $allocator;

    protected function setUp(): void
    {
        $this->allocator = new StockAllocator();
    }

    public function testAllocatesFromSingleWarehouseWhenPossible(): void
    {
        $result = $this->allocator->allocate(
            requested: [1 => 5],
            availability: [
                10 => [1 => 5],
                20 => [1 => 10],
            ],
        );

        self::assertTrue($result->isFullyFulfilled());
        self::assertSame([10 => [1 => 5]], $result->getAllocations());
        self::assertSame([], $result->getMissing());
    }

    public function testPrefersFewestWarehousesForMultiProductOrder(): void
    {
        $result = $this->allocator->allocate(
            requested: [1 => 3, 2 => 2],
            availability: [
                1 => [1 => 3, 2 => 2],
                2 => [1 => 10, 2 => 10],
                3 => [1 => 10],
            ],
        );

        self::assertTrue($result->isFullyFulfilled());
        self::assertSame([1 => [1 => 3, 2 => 2]], $result->getAllocations());
    }

    public function testFallsBackToSecondWarehouseWhenSingleWarehouseInsufficient(): void
    {
        $result = $this->allocator->allocate(
            requested: [1 => 7],
            availability: [
                1 => [1 => 5],
                2 => [1 => 5],
            ],
        );

        self::assertTrue($result->isFullyFulfilled());
        self::assertSame(2, $result->warehouseCount());
        self::assertSame(7, $result->totalAllocated());
    }

    public function testReportsMissingItemsForPartialFulfillment(): void
    {
        $result = $this->allocator->allocate(
            requested: [1 => 10],
            availability: [
                1 => [1 => 4],
                2 => [1 => 3],
            ],
        );

        self::assertFalse($result->isFullyFulfilled());
        self::assertSame([1 => 3], $result->getMissing());
        self::assertSame(7, $result->totalAllocated());
    }

    public function testReturnsEmptyAllocationWhenNoStockAvailable(): void
    {
        $result = $this->allocator->allocate(
            requested: [1 => 5],
            availability: [],
        );

        self::assertFalse($result->isFullyFulfilled());
        self::assertSame([1 => 5], $result->getMissing());
        self::assertSame([], $result->getAllocations());
    }

    #[DataProvider('multiProductPartialProvider')]
    public function testPartialMultiProductAllocation(array $requested, array $availability, array $expectedMissing, int $expectedAllocated): void
    {
        $result = $this->allocator->allocate($requested, $availability);

        self::assertSame($expectedMissing, $result->getMissing());
        self::assertSame($expectedAllocated, $result->totalAllocated());
    }

    public static function multiProductPartialProvider(): iterable
    {
        yield 'missing second product only' => [
            [1 => 2, 2 => 5],
            [
                1 => [1 => 2, 2 => 1],
                2 => [2 => 2],
            ],
            [2 => 2],
            5,
        ];
    }
}
