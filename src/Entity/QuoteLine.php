<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Une ligne de devis : un produit du catalogue ou une ligne libre. Le produit ne sert qu'à pré-remplir
// la ligne et à montrer sa photo : libellé, précisions et prix sont recopiés et restent modifiables à la main.
#[ORM\Entity]
class QuoteLine
{
    use DiscountTrait;   // porte sur le total de la ligne, pas sur chaque unité

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Supprimer le devis supprime ses lignes : Doctrine s'en charge, et la base aussi
    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quote $quote = null;

    // Vide pour une ligne libre. Un produit retiré du catalogue laisse la ligne intacte.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La désignation est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'La désignation ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $label = null;

    // Ex. « Tissu Chenille Monica, coloris Terra Cota - 1012 »
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le tissu ou la finition ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $finish = null;

    // Taille, dimensions du sur-mesure, sens de l'angle… une précision par ligne
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Les précisions ne peuvent pas dépasser {{ limit }} caractères.')]
    private ?string $details = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Indiquez la quantité.')]
    #[Assert\Range(notInRangeMessage: 'La quantité va de {{ min }} à {{ max }}.', min: 1, max: 999)]
    private ?int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Le prix est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    #[Assert\LessThanOrEqual(value: 50000, message: 'Le prix ne peut pas dépasser {{ compared_value }} €.')]
    private ?string $unitPrice = null;

    // « Fabriqué selon les choix du client » : pas de droit de rétractation (article L221-28, 3° du Code de la consommation)
    #[ORM\Column]
    private bool $customMade = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): static
    {
        $this->quote = $quote;

        return $this;
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getFinish(): ?string
    {
        return $this->finish;
    }

    public function setFinish(?string $finish): static
    {
        $this->finish = $finish;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function isCustomMade(): bool
    {
        return $this->customMade;
    }

    public function setCustomMade(bool $customMade): static
    {
        $this->customMade = $customMade;

        return $this;
    }
}
