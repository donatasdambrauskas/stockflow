<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use App\Repository\WarehouseStockRepository;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Scenario: 2 warehouses, 2 products.
 * Orders 1 and 2 are fully reserved and shipped.
 * Order 3 is partially reserved: product A is unavailable, remaining product B stock is only in warehouse 2.
 */
final class TwoWarehousesTwoProductsScenarioTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private OrderService $orderService;
    private WarehouseStockRepository $warehouseStockRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->orderService = $container->get(OrderService::class);
        $this->warehouseStockRepository = $container->get(WarehouseStockRepository::class);

        $this->seedCatalog();
    }

    public function testThirdOrderIsPartiallyReservedAfterTwoShippedOrders(): void
    {
        $order1 = $this->orderService->createOrder([
            ['sku' => 'PROD-A', 'quantity' => 10],
            ['sku' => 'PROD-B', 'quantity' => 5],
        ]);

        self::assertSame(OrderStatus::Reserved, $order1->getStatus());
        self::assertCount(2, $order1->getReservations());

        $shippedOrder1 = $this->orderService->shipOrder($order1->getId());
        self::assertSame(OrderStatus::Shipped, $shippedOrder1->getStatus());
        self::assertCount(0, $shippedOrder1->getReservations());

        $this->assertStock('WH-1', 'PROD-A', quantity: 0, reserved: 0, available: 0);
        $this->assertStock('WH-1', 'PROD-B', quantity: 0, reserved: 0, available: 0);
        $this->assertStock('WH-2', 'PROD-A', quantity: 5, reserved: 0, available: 5);
        $this->assertStock('WH-2', 'PROD-B', quantity: 2, reserved: 0, available: 2);

        $order2 = $this->orderService->createOrder([
            ['sku' => 'PROD-A', 'quantity' => 5],
        ]);

        self::assertSame(OrderStatus::Reserved, $order2->getStatus());

        $shippedOrder2 = $this->orderService->shipOrder($order2->getId());
        self::assertSame(OrderStatus::Shipped, $shippedOrder2->getStatus());

        $this->assertStock('WH-2', 'PROD-A', quantity: 0, reserved: 0, available: 0);
        $this->assertStock('WH-2', 'PROD-B', quantity: 2, reserved: 0, available: 2);

        $order3 = $this->orderService->createOrder([
            ['sku' => 'PROD-A', 'quantity' => 2],
            ['sku' => 'PROD-B', 'quantity' => 5],
        ]);

        self::assertSame(OrderStatus::PartiallyReserved, $order3->getStatus());

        $itemsBySku = [];
        foreach ($order3->getItems() as $item) {
            $itemsBySku[$item->getProduct()->getSku()] = $item;
        }

        self::assertSame(0, $itemsBySku['PROD-A']->getQuantityReserved());
        self::assertSame(2, $itemsBySku['PROD-A']->getMissingQuantity());
        self::assertSame(2, $itemsBySku['PROD-B']->getQuantityReserved());
        self::assertSame(3, $itemsBySku['PROD-B']->getMissingQuantity());

        self::assertCount(1, $order3->getReservations());
        $reservation = $order3->getReservations()->first();
        self::assertNotFalse($reservation);
        self::assertSame('WH-2', $reservation->getWarehouse()->getCode());
        self::assertSame('PROD-B', $reservation->getProduct()->getSku());
        self::assertSame(2, $reservation->getQuantity());

        $this->assertStock('WH-2', 'PROD-B', quantity: 2, reserved: 2, available: 0);
    }

    private function seedCatalog(): void
    {
        $warehouse1 = new Warehouse('WH-1', 'Warehouse 1');
        $warehouse2 = new Warehouse('WH-2', 'Warehouse 2');
        $productA = new Product('PROD-A', 'Product A');
        $productB = new Product('PROD-B', 'Product B');

        $this->entityManager->persist($warehouse1);
        $this->entityManager->persist($warehouse2);
        $this->entityManager->persist($productA);
        $this->entityManager->persist($productB);

        $this->entityManager->persist(new WarehouseStock($warehouse1, $productA, 10));
        $this->entityManager->persist(new WarehouseStock($warehouse1, $productB, 5));
        $this->entityManager->persist(new WarehouseStock($warehouse2, $productA, 5));
        $this->entityManager->persist(new WarehouseStock($warehouse2, $productB, 2));

        $this->entityManager->flush();
    }

    private function assertStock(
        string $warehouseCode,
        string $productSku,
        int $quantity,
        int $reserved,
        int $available,
    ): void {
        $stock = $this->warehouseStockRepository->createQueryBuilder('ws')
            ->join('ws.warehouse', 'w')
            ->join('ws.product', 'p')
            ->where('w.code = :warehouseCode')
            ->andWhere('p.sku = :productSku')
            ->setParameter('warehouseCode', $warehouseCode)
            ->setParameter('productSku', $productSku)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertNotNull($stock, sprintf('Stock row for %s / %s not found.', $warehouseCode, $productSku));
        self::assertSame($quantity, $stock->getQuantity(), sprintf('%s/%s quantity', $warehouseCode, $productSku));
        self::assertSame($reserved, $stock->getReservedQuantity(), sprintf('%s/%s reserved', $warehouseCode, $productSku));
        self::assertSame($available, $stock->getAvailableQuantity(), sprintf('%s/%s available', $warehouseCode, $productSku));
    }
}
