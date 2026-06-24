<?php

namespace App\Allocation;

/**
 * Allocates stock from the minimum number of warehouses.
 * Pure domain logic suitable for unit testing without the database.
 */
final class StockAllocator
{
    /**
     * @param array<int, int>               $requested    productId => quantity
     * @param array<int, array<int, int>>   $availability warehouseId => [productId => availableQty]
     */
    public function allocate(array $requested, array $availability): AllocationResult
    {
        if ($requested === []) {
            return new AllocationResult([], []);
        }

        $warehouseIds = array_keys($availability);

        if ($warehouseIds === []) {
            return new AllocationResult([], AllocationResult::emptyMissing($requested));
        }

        $bestResult = null;

        for ($size = 1; $size <= \count($warehouseIds); ++$size) {
            foreach ($this->combinations($warehouseIds, $size) as $combination) {
                $candidate = $this->allocateFromWarehouses($requested, $availability, $combination);

                if ($bestResult === null || $this->isBetter($candidate, $bestResult)) {
                    $bestResult = $candidate;
                }

                if ($candidate->isFullyFulfilled()) {
                    return $candidate;
                }
            }

            if ($bestResult !== null && $bestResult->isFullyFulfilled()) {
                return $bestResult;
            }
        }

        return $bestResult ?? new AllocationResult([], AllocationResult::emptyMissing($requested));
    }

    /**
     * @param array<int, int>             $requested
     * @param array<int, array<int, int>> $availability
     * @param list<int>                   $warehouseIds
     */
    private function allocateFromWarehouses(array $requested, array $availability, array $warehouseIds): AllocationResult
    {
        $allocations = [];
        $missing = [];

        foreach ($requested as $productId => $quantityNeeded) {
            $remaining = $quantityNeeded;

            $sortedWarehouses = $warehouseIds;
            usort(
                $sortedWarehouses,
                static fn (int $a, int $b): int => ($availability[$b][$productId] ?? 0) <=> ($availability[$a][$productId] ?? 0),
            );

            foreach ($sortedWarehouses as $warehouseId) {
                if ($remaining <= 0) {
                    break;
                }

                $available = $availability[$warehouseId][$productId] ?? 0;

                if ($available <= 0) {
                    continue;
                }

                $allocated = min($available, $remaining);
                $allocations[$warehouseId][$productId] = ($allocations[$warehouseId][$productId] ?? 0) + $allocated;
                $remaining -= $allocated;
            }

            if ($remaining > 0) {
                $missing[$productId] = $remaining;
            }
        }

        return new AllocationResult($allocations, $missing);
    }

    private function isBetter(AllocationResult $candidate, AllocationResult $current): bool
    {
        if ($candidate->totalAllocated() !== $current->totalAllocated()) {
            return $candidate->totalAllocated() > $current->totalAllocated();
        }

        if ($candidate->warehouseCount() !== $current->warehouseCount()) {
            return $candidate->warehouseCount() < $current->warehouseCount();
        }

        return false;
    }

    /**
     * @param list<int> $items
     *
     * @return \Generator<int, list<int>>
     */
    private function combinations(array $items, int $size): \Generator
    {
        $count = \count($items);

        if ($size <= 0 || $size > $count) {
            return;
        }

        $indices = range(0, $size - 1);

        while (true) {
            $combination = [];
            foreach ($indices as $index) {
                $combination[] = $items[$index];
            }

            yield $combination;

            $position = $size - 1;
            while ($position >= 0 && $indices[$position] === $position + $count - $size) {
                --$position;
            }

            if ($position < 0) {
                break;
            }

            ++$indices[$position];
            for ($next = $position + 1; $next < $size; ++$next) {
                $indices[$next] = $indices[$next - 1] + 1;
            }
        }
    }
}
