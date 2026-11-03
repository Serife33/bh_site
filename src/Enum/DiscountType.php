<?php

namespace App\Enum;

// Une remise s'exprime en pourcentage, en euros TTC, ou sur une ligne par son prix final unitaire TTC :
// le vendeur cède le canapé à 2 000 € au lieu de 2 400 €, la remise de 400 € se calcule seule (QuoteCalculator)
enum DiscountType: string
{
    case Percent = 'percent';
    case Amount = 'amount';
    case FinalPrice = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Percent    => '%',
            self::Amount     => '€',
            self::FinalPrice => 'Prix final',
        };
    }
}
