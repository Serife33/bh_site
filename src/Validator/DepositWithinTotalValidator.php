<?php

namespace App\Validator;

use App\Entity\Quote;
use App\Service\QuoteCalculator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

// Le total TTC vient de QuoteCalculator : si les lignes baissent sous l'acompte saisi, l'enregistrement est refusé
final class DepositWithinTotalValidator extends ConstraintValidator
{
    public function __construct(private QuoteCalculator $calculator)
    {
    }

    /**
     * @param Quote $quote
     */
    public function validate(mixed $quote, Constraint $constraint): void
    {
        if ($quote->getDepositAmount() !== null && bccomp($quote->getDepositAmount(), $this->calculator->totals($quote)['ttc'], 2) > 0) {
            $this->context->buildViolation($constraint->message)->atPath('depositAmount')->addViolation();
        }
    }
}
