<?php

namespace App\Twig\Runtime;

use App\Repository\CategoryRepository;
use App\Service\QuoteCalculator;
use Twig\Extension\RuntimeExtensionInterface;

class AppExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private QuoteCalculator $quoteCalculator,
    ) {
    }

    public function getNavCategories(): array
    {
        return $this->categoryRepository->findForMenu();
    }

    // Le HT d'un montant TTC au taux du devis, pour les lignes de la facture : le calcul reste celui de QuoteCalculator
    public function ht(string $ttc, string $vatRate): string
    {
        return $this->quoteCalculator->excludingVat($ttc, $vatRate);
    }
}
