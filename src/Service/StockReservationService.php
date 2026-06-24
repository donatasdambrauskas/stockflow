<?php

namespace App\Service;

use App\Allocation\StockAllocator;
use App\Entity\Order;
use App\Entity\OrderStatus;
use App\Entity\StockReservation;
use App\Repository\OrderRepository;
use App\Repository\WarehouseStockRepository;
use Doctrine\ORM\EntityManagerInterface;

final class StockReservationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WarehouseStockRepository $warehouseStockRepository,
        private readonly OrderRepository $orderRepository,
        private readonly StockAllocator $stockAllocator,
    ) {
    }

    public function reserveOrder(Order $order): void
    {
        $this->clearOrderReservations($order);

        $requested = $this->buildRequestedMap($order);
        $availability = $this->buildAvailabilityMap(array_keys($requested));
        $result = $this->stockAllocator->allocate($requested, $availability);

        $stockIndex = $this->indexStocksByWarehouseAndProduct(array_keys($requested));

        foreach ($result->getAllocations() as $warehouseId => $products) {
            foreach ($products as $productId => $quantity) {
                $stock = $stockIndex[$warehouseId][$productId] ?? null;

                if ($stock === null) {
                    continue;
                }

                $stock->reserve($quantity);
                $reservation = new StockReservation($stock->getWarehouse(), $stock->getProduct(), $quantity);
                $order->addReservation($reservation);
            }
        }

        foreach ($order->getItems() as $item) {
            $productId = $item->getProduct()->getId();
            $reserved = ($requested[$productId] ?? 0) - ($result->getMissing()[$productId] ?? 0);
            $item->setQuantityReserved($reserved);
        }

        if ($result->isFullyFulfilled()) {
            $order->setStatus(OrderStatus::Reserved);
        } elseif ($result->totalAllocated() > 0) {
            $order->setStatus(OrderStatus::PartiallyReserved);
        } else {
            $order->setStatus(OrderStatus::Pending);
        }
    }

    public function releaseOrderReservations(Order $order): void
    {
        $this->clearOrderReservations($order);

        foreach ($order->getItems() as $item) {
            $item->setQuantityReserved(0);
        }
    }

    public function shipOrder(Order $order): void
    {
        if ($order->getStatus() === OrderStatus::Shipped) {
            throw new \DomainException('Order is already shipped.');
        }

        if ($order->getStatus() === OrderStatus::Cancelled) {
            throw new \DomainException('Cancelled orders cannot be shipped.');
        }

        if (!\in_array($order->getStatus(), [OrderStatus::Reserved, OrderStatus::PartiallyReserved], true)) {
            throw new \DomainException('Order has no reservations to ship.');
        }

        $stockIndex = $this->indexStocksByWarehouseAndProduct(
            array_map(static fn ($item) => $item->getProduct()->getId(), $order->getItems()->toArray()),
        );

        foreach ($order->getReservations() as $reservation) {
            $warehouseId = $reservation->getWarehouse()->getId();
            $productId = $reservation->getProduct()->getId();
            $stock = $stockIndex[$warehouseId][$productId] ?? null;

            if ($stock === null) {
                throw new \RuntimeException('Reserved stock record not found.');
            }

            $stock->ship($reservation->getQuantity());
        }

        $order->clearReservations();
        $order->setStatus(OrderStatus::Shipped);
    }

    public function recalculateActiveOrders(): void
    {
        $orders = $this->orderRepository->findActiveForRecalculation();

        foreach ($orders as $order) {
            $this->clearOrderReservations($order);
        }

        $this->entityManager->flush();

        foreach ($orders as $order) {
            $this->reserveOrder($order);
        }
    }

    private function clearOrderReservations(Order $order): void
    {
        $productIds = array_map(
            static fn ($reservation) => $reservation->getProduct()->getId(),
            $order->getReservations()->toArray(),
        );

        if ($productIds === []) {
            $order->clearReservations();

            return;
        }

        $stockIndex = $this->indexStocksByWarehouseAndProduct($productIds);

        foreach ($order->getReservations() as $reservation) {
            $warehouseId = $reservation->getWarehouse()->getId();
            $productId = $reservation->getProduct()->getId();
            $stock = $stockIndex[$warehouseId][$productId] ?? null;

            if ($stock !== null) {
                $stock->releaseReservation($reservation->getQuantity());
            }
        }

        $order->clearReservations();
    }

    /**
     * @return array<int, int>
     */
    private function buildRequestedMap(Order $order): array
    {
        $requested = [];

        foreach ($order->getItems() as $item) {
            $productId = $item->getProduct()->getId();
            $requested[$productId] = ($requested[$productId] ?? 0) + $item->getQuantityRequested();
        }

        return $requested;
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, array<int, int>>
     */
    private function buildAvailabilityMap(array $productIds): array
    {
        $availability = [];

        foreach ($this->warehouseStockRepository->findAvailableForProducts($productIds) as $stock) {
            $warehouseId = $stock->getWarehouse()->getId();
            $productId = $stock->getProduct()->getId();
            $availability[$warehouseId][$productId] = $stock->getAvailableQuantity();
        }

        return $availability;
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, array<int, \App\Entity\WarehouseStock>>
     */
    private function indexStocksByWarehouseAndProduct(array $productIds): array
    {
        $index = [];

        foreach ($this->warehouseStockRepository->findAvailableForProducts($productIds) as $stock) {
            $warehouseId = $stock->getWarehouse()->getId();
            $productId = $stock->getProduct()->getId();
            $index[$warehouseId][$productId] = $stock;
        }

        // Include zero-availability rows for release/ship operations.
        if ($productIds !== []) {
            $allStocks = $this->warehouseStockRepository->createQueryBuilder('ws')
                ->addSelect('w', 'p')
                ->join('ws.warehouse', 'w')
                ->join('ws.product', 'p')
                ->where('p.id IN (:productIds)')
                ->setParameter('productIds', $productIds)
                ->getQuery()
                ->getResult();

            foreach ($allStocks as $stock) {
                $warehouseId = $stock->getWarehouse()->getId();
                $productId = $stock->getProduct()->getId();
                $index[$warehouseId][$productId] = $stock;
            }
        }

        return $index;
    }
}
