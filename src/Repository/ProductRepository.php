<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\SubCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }


    public function findAllOrderedQuery(): Query
    {
        return $this->createQueryBuilder('p') // 'p' = alias du produit dans la requete
            ->select('p.id', 'p.name', 'p.actualPrice', 'p.stock') // ← projection : que les colonnes de la liste
            ->orderBy('p.position', 'ASC') // tri par posititon (ordre d'affichage) croissant 
            ->getQuery() // Query pas getResult(), la query n'est pas executée 
        ;
    }


    // Les derniers produits actifs (pour la grille "Nouveautés" de l'accueil).
    public function findLatestActive(int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = true')   // seulement les produits publiés
            ->orderBy('p.createdAt', 'DESC')   // les plus récents d'abord
            ->setMaxResults($limit)            // "limit" produits max
            ->getQuery()
            ->getResult();
    }

    // Produits en promo (prix actuel < prix initial)
    public function findOnSale(int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = true')
            ->andWhere('p.actualPrice < p.initialPrice')   // ← la promo
            ->orderBy('p.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //Produits disponibles immédiatement (stock > 0)
    public function findInStock(int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = true')
            ->andWhere('p.stock > 0')                       // ← en stock
            ->orderBy('p.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    public function findActiveByCategoryQuery(Category $category, ?SubCategory $subCategory = null): Query
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = true')
            ->andWhere('p.category = :category')
            ->setParameter('category', $category)
            ->orderBy('p.position', 'ASC');

        // Filtre optionnel : seulement si une sous-catégorie est choisie
        if ($subCategory !== null) {
            $qb->join('p.subCategories', 'sc')
            ->andWhere('sc = :subCategory')
            ->setParameter('subCategory', $subCategory);
        }

        return $qb->getQuery();
    }

    public function findSimilar(Product $product, int $limit = 4): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.subCategories', 'sc')
            ->andWhere('p.isActive = true')
            ->andWhere('p != :current')          // exclure le produit lui-même
            ->setParameter('current', $product)
            ->setMaxResults($limit)
            ->distinct();

        // Similarité : même sous-catégorie OU même famille
        $ou = $qb->expr()->orX();

        $sousCatIds = $product->getSubCategories()->map(fn($sc) => $sc->getId())->toArray();
        if ($sousCatIds) {
            $ou->add('sc.id IN (:sousCatIds)');
            $qb->setParameter('sousCatIds', $sousCatIds);
        }
        if ($product->getFamily()) {
            $ou->add('p.family = :family');
            $qb->setParameter('family', $product->getFamily());
        }

        if ($ou->count() === 0) {
            return [];   // ni sous-cat ni famille → aucun similaire
        }

        return $qb->andWhere($ou)->getQuery()->getResult();
    }

}
