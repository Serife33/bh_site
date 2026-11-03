<?php

namespace App\Twig\Runtime;

use App\Repository\AnnouncementRepository;
use App\Repository\CategoryRepository;
use Twig\Extension\RuntimeExtensionInterface;

class AppExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private AnnouncementRepository $announcementRepository,
    ) {
    }

    public function getNavCategories(): array
    {
        return $this->categoryRepository->findForMenu();
    }

    // Les messages du bandeau ce jour, à l'heure de Paris (le projet ne fixe aucun fuseau)
    public function getBandAnnouncements(): array
    {
        return $this->announcementRepository->findVisibleOn(new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')));
    }
}