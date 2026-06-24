<?php

namespace App\Allocation;

final readonly class AllocationResult
{
    /**
     * @param array<int, array<int, int>> $allocations warehouseId => [productId => quantity]
     * @param array<int, int>             $missing     productId => unfulfilled quantity
     */
    public function __construct(
        private array $allocations,
        private array $missing,
    ) {
    }

    /** @return array<int, array<int, int>> */
    public function getAllocations(): array
    {
        return $this->allocations;
    }

    /** @return array<int, int> */
    public function getMissing(): array
    {
        return $this->missing;
    }

    public function isFullyFulfilled(): bool
    {
        return $this->missing === [];
    }

    public function warehouseCount(): int
    {
        return \count(array_filter(
            $this->allocations,
            static fn (array $products): bool => array_sum($products) > 0,
        ));
    }

    public function totalAllocated(): int
    {
        $total = 0;

        foreach ($this->allocations as $products) {
            $total += array_sum($products);
        }

        return $total;
    }

    /**
     * @param array<int, int> $requested
     *
     * @return array<int, int>
     */
    public static function emptyMissing(array $requested): array
    {
        return $requested;
    }
}
