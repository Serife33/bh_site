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
            ->select('c.id', 'c.name', 'c.slug', 'c.isVisible', 'c.seoText', 'c.metaTitle', 'c.metaDescription')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult(); // tableaux bruts, pas d'objets Color
    }

    // Le menu du site, « Nos univers » de l'accueil et le plan du site : les catégories visibles, dans l'ordre de création
    public function findForMenu(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isVisible = true')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Les menus déroulants de l'admin : des objets, triés par nom
    public function findSortedByName(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // La page d'une catégorie : une catégorie masquée ne s'ouvre pas
    public function findOneBySlug(string $slug): ?Category
    {
        return $this->findOneBy(['slug' => $slug, 'isVisible' => true]);
    }

    // L'import retrouve la catégorie écrite en toutes lettres dans le tableur
    public function findOneByName(string $name): ?Category
    {
        return $this->findOneBy(['name' => $name]);
    }
}
