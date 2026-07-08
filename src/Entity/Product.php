<?php

namespace App\Entity;

use App\Enum\ProductModular;
use App\Enum\ProductSide;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private ?string $name = null;

    #[ORM\Column(length: 180)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $dimension = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $initialPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $actualPrice = null;

    #[ORM\Column]
    private ?int $stock = null;

    #[ORM\Column]
    private ?bool $isCustomMade = null;

    #[ORM\Column(enumType: ProductModular::class)]
    private ?ProductModular $isModular = null;

    #[ORM\Column(enumType: ProductSide::class)]
    private ?ProductSide $sideLr = null;

    #[ORM\Column(nullable: true)]
    private ?int $leadMinWeeks = null;

    #[ORM\Column(nullable: true)]
    private ?int $leadMaxWeeks = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column]
    private ?int $position = null;

    #[ORM\Column]
    private ?bool $isActive = null;

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDimension(): ?string
    {
        return $this->dimension;
    }

    public function setDimension(?string $dimension): static
    {
        $this->dimension = $dimension;

        return $this;
    }

    public function getInitialPrice(): ?string
    {
        return $this->initialPrice;
    }

    public function setInitialPrice(string $initialPrice): static
    {
        $this->initialPrice = $initialPrice;

        return $this;
    }

    public function getActualPrice(): ?string
    {
        return $this->actualPrice;
    }

    public function setActualPrice(string $actualPrice): static
    {
        $this->actualPrice = $actualPrice;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function isCustomMade(): ?bool
    {
        return $this->isCustomMade;
    }

    public function setIsCustomMade(bool $isCustomMade): static
    {
        $this->isCustomMade = $isCustomMade;

        return $this;
    }

    public function getIsModular(): ?ProductModular
    {
        return $this->isModular;
    }

    public function setIsModular(ProductModular $isModular): static
    {
        $this->isModular = $isModular;

        return $this;
    }

    public function getSideLr(): ?ProductSide
    {
        return $this->sideLr;
    }

    public function setSideLr(ProductSide $sideLr): static
    {
        $this->sideLr = $sideLr;

        return $this;
    }

    public function getLeadMinWeeks(): ?int
    {
        return $this->leadMinWeeks;
    }

    public function setLeadMinWeeks(?int $leadMinWeeks): static
    {
        $this->leadMinWeeks = $leadMinWeeks;

        return $this;
    }

    public function getLeadMaxWeeks(): ?int
    {
        return $this->leadMaxWeeks;
    }

    public function setLeadMaxWeeks(?int $leadMaxWeeks): static
    {
        $this->leadMaxWeeks = $leadMaxWeeks;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
