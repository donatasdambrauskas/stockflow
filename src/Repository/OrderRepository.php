<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Order> */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /** @return list<Order> */
    public function findActiveForRecalculation(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', [OrderStatus::PartiallyReserved, OrderStatus::Reserved])
            ->orderBy('o.createdAt', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
