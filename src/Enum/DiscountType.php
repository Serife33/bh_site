<?php

namespace App\Enum;

// Une remise s'exprime en pourcentage ou en euros TTC
enum DiscountType: string
{
    case Percent = 'percent';
    case Amount = 'amount';

    public function symbol(): string
    {
        return $this === self::Percent ? '%' : '€';
    }
}
