<?php

namespace App\Twig\Runtime;

use App\Entity\Announcement;
use App\Repository\AnnouncementRepository;
use App\Repository\CategoryRepository;
use App\Service\QuoteCalculator;
use Twig\Extension\RuntimeExtensionInterface;

class AppExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private AnnouncementRepository $announcementRepository,
        private QuoteCalculator $quoteCalculator,
    ) {
    }

    public function getNavCategories(): array
    {
        return $this->categoryRepository->findForMenu();
    }

    public function getBandAnnouncements(): array
    {
        return $this->announcementRepository->findVisibleOn(Announcement::today());
    }

    // Le HT d'un montant TTC au taux du devis, pour les lignes de la facture : le calcul reste celui de QuoteCalculator
    public function ht(string $ttc, string $vatRate): string
    {
        return $this->quoteCalculator->excludingVat($ttc, $vatRate);
    }
}
