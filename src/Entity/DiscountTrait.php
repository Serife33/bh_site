<?php

namespace App\Entity;

use App\Enum\DiscountType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// La remise d'une ligne de devis ou du devis entier : un pourcentage, ou un montant en euros TTC.
// Le calcul est fait par QuoteCalculator.
trait DiscountTrait
{
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Indiquez la remise, 0 sinon.')]
    #[Assert\PositiveOrZero(message: 'Une remise ne peut pas être négative.')]
    #[Assert\LessThanOrEqual(value: 50000, message: 'Remise invraisemblable : {{ compared_value }} maximum.')]
    #[Assert\When(
        expression: 'this.getDiscountType().value == "percent"',
        constraints: [new Assert\LessThanOrEqual(value: 100, message: 'Une remise ne peut pas dépasser 100 %.')],
    )]
    private ?string $discountValue = '0.00';

    #[ORM\Column(enumType: DiscountType::class)]
    private DiscountType $discountType = DiscountType::Percent;

    public function getDiscountValue(): ?string
    {
        return $this->discountValue;
    }

    public function setDiscountValue(?string $discountValue): static
    {
        $this->discountValue = $discountValue;

        return $this;
    }

    public function getDiscountType(): DiscountType
    {
        return $this->discountType;
    }

    public function setDiscountType(DiscountType $discountType): static
    {
        $this->discountType = $discountType;

        return $this;
    }
}
