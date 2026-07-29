<?php

namespace App\Twig\Runtime;

use App\Repository\CategoryRepository;
use Twig\Extension\RuntimeExtensionInterface;

class AppExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    public function getNavCategories(): array
    {
        return $this->categoryRepository->findBy([], ['id' => 'ASC']);
    }
}