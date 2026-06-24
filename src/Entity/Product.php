<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $sku;

    #[ORM\Column(length: 255)]
    private string $name;

    /** @var Collection<int, WarehouseStock> */
    #[ORM\OneToMany(targetEntity: WarehouseStock::class, mappedBy: 'product', orphanRemoval: true)]
    private Collection $warehouseStocks;

    public function __construct(string $sku, string $name)
    {
        $this->sku = $sku;
        $this->name = $name;
        $this->warehouseStocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Collection<int, WarehouseStock> */
    public function getWarehouseStocks(): Collection
    {
        return $this->warehouseStocks;
    }
}
