<?php

namespace App\Twig\Runtime;

use App\Entity\Announcement;
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

    public function getBandAnnouncements(): array
    {
        return $this->announcementRepository->findVisibleOn(Announcement::today());
    }
}