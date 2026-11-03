<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Une combinaison vendable d'un produit, avec son propre prix : une taille, une matière, ou les deux.
#[ORM\Entity]
#[Assert\Expression(
    'this.getSize() or this.getFabric()',
    message: 'Une variante doit avoir au moins une taille ou une matière.',
)]
class ProductVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Supprimer le produit supprime ses variantes : Doctrine s'en charge, et la base aussi si la suppression se fait hors Doctrine
    #[ORM\ManyToOne(inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    // Ex. « 160x200 »
    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30, maxMessage: 'La taille ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $size = null;

    // La matière qui change le prix (ex. céramique sur une table).
    // Les tissus proposés sans effet sur le prix restent dans Product::$fabrics.
    #[ORM\ManyToOne]
    private ?Fabric $fabric = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Le prix initial est obligatoire.')]
    #[Assert\Positive(message: 'Le prix doit être supérieur à 0.')]
    #[Assert\LessThanOrEqual(value: 50000, message: 'Le prix ne peut pas dépasser {{ compared_value }} €.')]
    private ?string $initialPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Le prix actuel est obligatoire.')]
    #[Assert\Positive(message: 'Le prix doit être supérieur à 0.')]
    #[Assert\LessThanOrEqual(
        propertyPath: 'initialPrice',
        message: 'Le prix actuel ne peut pas dépasser le prix initial.'
    )]
    private ?string $actualPrice = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\NotNull(message: 'Le stock est obligatoire (0 = sur commande).')]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    #[Assert\LessThanOrEqual(value: 9999, message: 'Stock invraisemblable : {{ compared_value }} maximum.')]
    private ?int $stock = 0;

    // Ordre d'affichage sur la fiche
    #[ORM\Column]
    private ?int $position = 1;

    // L'ancienne adresse d'une fiche regroupée dans cette variante, pour sa redirection 301
    #[ORM\Column(length: 180, nullable: true, unique: true)]
    private ?string $legacySlug = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(?string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getFabric(): ?Fabric
    {
        return $this->fabric;
    }

    public function setFabric(?Fabric $fabric): static
    {
        $this->fabric = $fabric;

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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getLegacySlug(): ?string
    {
        return $this->legacySlug;
    }

    public function setLegacySlug(?string $legacySlug): static
    {
        $this->legacySlug = $legacySlug;

        return $this;
    }
}
