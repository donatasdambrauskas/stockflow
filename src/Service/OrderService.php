<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

final class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $productRepository,
        private readonly OrderRepository $orderRepository,
        private readonly StockReservationService $stockReservationService,
    ) {
    }

    /**
     * @param list<array{sku: string, quantity: int}> $items
     */
    public function createOrder(array $items): Order
    {
        if ($items === []) {
            throw new \InvalidArgumentException('Order must contain at least one item.');
        }

        $order = new Order();

        foreach ($items as $item) {
            $product = $this->productRepository->findOneBySku($item['sku']);

            if ($product === null) {
                throw new \InvalidArgumentException(sprintf('Unknown product SKU: %s', $item['sku']));
            }

            if ($item['quantity'] <= 0) {
                throw new \InvalidArgumentException('Item quantity must be positive.');
            }

            $order->addItem(new OrderItem($product, $item['quantity']));
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->stockReservationService->reserveOrder($order);
        $this->entityManager->flush();

        return $order;
    }

    public function getOrder(int $id): Order
    {
        $order = $this->orderRepository->find($id);

        if ($order === null) {
            throw new \InvalidArgumentException(sprintf('Order %d not found.', $id));
        }

        return $order;
    }

    public function shipOrder(int $id): Order
    {
        $order = $this->getOrder($id);
        $this->stockReservationService->shipOrder($order);
        $this->entityManager->flush();

        return $order;
    }

    public function cancelOrder(int $id): Order
    {
        $order = $this->getOrder($id);

        if ($order->getStatus() === OrderStatus::Shipped) {
            throw new \DomainException('Shipped orders cannot be cancelled.');
        }

        if ($order->getStatus() === OrderStatus::Cancelled) {
            throw new \DomainException('Order is already cancelled.');
        }

        $this->stockReservationService->releaseOrderReservations($order);
        $order->setStatus(OrderStatus::Cancelled);
        $this->entityManager->flush();

        $this->stockReservationService->recalculateActiveOrders();
        $this->entityManager->flush();

        return $order;
    }
}
