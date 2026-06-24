<?php

namespace App\Entity;

use App\Repository\WarehouseStockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WarehouseStockRepository::class)]
#[ORM\Table(name: 'warehouse_stocks')]
#[ORM\UniqueConstraint(name: 'uniq_warehouse_product', columns: ['warehouse_id', 'product_id'])]
class WarehouseStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stocks')]
    #[ORM\JoinColumn(nullable: false)]
    private Warehouse $warehouse;

    #[ORM\ManyToOne(inversedBy: 'warehouseStocks')]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column]
    private int $quantity = 0;

    #[ORM\Column]
    private int $reservedQuantity = 0;

    public function __construct(Warehouse $warehouse, Product $product, int $quantity = 0)
    {
        $this->warehouse = $warehouse;
        $this->product = $product;
        $this->quantity = $quantity;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getReservedQuantity(): int
    {
        return $this->reservedQuantity;
    }

    public function getAvailableQuantity(): int
    {
        return $this->quantity - $this->reservedQuantity;
    }

    public function reserve(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Reservation amount must be positive.');
        }

        if ($amount > $this->getAvailableQuantity()) {
            throw new \RuntimeException('Insufficient available stock.');
        }

        $this->reservedQuantity += $amount;
    }

    public function releaseReservation(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Release amount must be positive.');
        }

        if ($amount > $this->reservedQuantity) {
            throw new \RuntimeException('Cannot release more than reserved quantity.');
        }

        $this->reservedQuantity -= $amount;
    }

    public function ship(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Ship amount must be positive.');
        }

        if ($amount > $this->reservedQuantity) {
            throw new \RuntimeException('Cannot ship more than reserved quantity.');
        }

        $this->quantity -= $amount;
        $this->reservedQuantity -= $amount;
    }
}
