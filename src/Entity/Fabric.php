<?php

namespace App\Entity;

use App\Repository\FabricRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FabricRepository::class)]
class Fabric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private ?string $name = null;

    // Référence fournisseur, ex. « GENOVA »
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $supplier = null;

    /**
     * @var Collection<int, FabricColor>
     */
    #[ORM\OneToMany(targetEntity: FabricColor::class, mappedBy: 'fabric', orphanRemoval: true)]
        #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $fabricColors;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'fabrics')]
    private Collection $products;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->fabricColors = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->addFabric($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            $product->removeFabric($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, FabricColor>
     */
    public function getFabricColors(): Collection
    {
        return $this->fabricColors;
    }

    public function addFabricColor(FabricColor $fabricColor): static
    {
        if (!$this->fabricColors->contains($fabricColor)) {
            $this->fabricColors->add($fabricColor);
            $fabricColor->setFabric($this);
        }

        return $this;
    }

    public function removeFabricColor(FabricColor $fabricColor): static
    {
        if ($this->fabricColors->removeElement($fabricColor)) {
            // on annule le lien seulement s'il pointait bien vers ce tissu
            if ($fabricColor->getFabric() === $this) {
                $fabricColor->setFabric(null);
            }
        }

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getSupplier(): ?string
    {
        return $this->supplier;
    }

    public function setSupplier(?string $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function __toString(): string
    {
        return $this->reference ? "{$this->name} ({$this->reference})" : ($this->name ?? '');
    }
}
