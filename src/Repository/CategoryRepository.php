<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    // Liste pour l'index admin : uniquement les colonnes affichées, triées par nom.
    // Projection → renvoie des tableaux (pas d'objets) : aucun lazy loading possible.
    
    public function findForIndex(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.id', 'c.name', 'c.slug', 'c.seoText', 'c.metaTitle', 'c.metaDescription')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult(); // tableaux bruts, pas d'objets Color
    }
 
}
