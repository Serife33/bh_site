<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

// L'acompte saisi sur un devis ne dépasse pas son total TTC, revérifié à chaque enregistrement
#[\Attribute(\Attribute::TARGET_CLASS)]
final class DepositWithinTotal extends Constraint
{
    public string $message = "L'acompte ne peut pas dépasser le total TTC.";

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
