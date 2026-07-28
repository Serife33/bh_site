<?php

namespace App\Repository;

use App\Entity\Product;
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

}
