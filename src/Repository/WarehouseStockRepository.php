<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Warehouse;
use App\Entity\WarehouseStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<WarehouseStock> */
class WarehouseStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WarehouseStock::class);
    }

    public function findOneByWarehouseAndProduct(Warehouse $warehouse, Product $product): ?WarehouseStock
    {
        return $this->findOneBy([
            'warehouse' => $warehouse,
            'product' => $product,
        ]);
    }

    /**
     * @param list<int> $productIds
     *
     * @return list<WarehouseStock>
     */
    public function findAvailableForProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return $this->createQueryBuilder('ws')
            ->addSelect('w', 'p')
            ->join('ws.warehouse', 'w')
            ->join('ws.product', 'p')
            ->where('p.id IN (:productIds)')
            ->andWhere('ws.quantity > ws.reservedQuantity')
            ->setParameter('productIds', $productIds)
            ->getQuery()
            ->getResult();
    }
}
