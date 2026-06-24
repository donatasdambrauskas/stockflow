<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\Table(name: 'order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private Order $order;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column]
    private int $quantityRequested;

    #[ORM\Column]
    private int $quantityReserved = 0;

    public function __construct(Product $product, int $quantityRequested)
    {
        $this->product = $product;
        $this->quantityRequested = $quantityRequested;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantityRequested(): int
    {
        return $this->quantityRequested;
    }

    public function getQuantityReserved(): int
    {
        return $this->quantityReserved;
    }

    public function setQuantityReserved(int $quantityReserved): void
    {
        $this->quantityReserved = $quantityReserved;
    }

    public function getMissingQuantity(): int
    {
        return max(0, $this->quantityRequested - $this->quantityReserved);
    }
}
