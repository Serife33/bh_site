<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\SubCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubCategory>
 */
class SubCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubCategory::class);
    }

    // Liste pour l'index admin : uniquement les colonnes affichées, triées par nom.
    // Projection → renvoie des tableaux (pas d'objets) : aucun lazy loading possible.
    
    public function findForIndex(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.id', 's.name', 's.slug')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function findUsedInCategory(Category $category): array
    {
        return $this->createQueryBuilder('sc')
            ->join('sc.products', 'p')          // relie sous-catégorie ↔ produits (ManyToMany)
            ->andWhere('p.category = :category') // …dont le produit est dans CETTE catégorie
            ->andWhere('p.isActive = true')      // …et publié
            ->setParameter('category', $category)
            ->distinct()                         // une sous-cat une seule fois (même si plusieurs produits)
            ->orderBy('sc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

}
