<?php

namespace App\Repository;

use App\Entity\Fabric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fabric>
 */
class FabricRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fabric::class);
    }

    
    // Liste pour l'index admin : uniquement les colonnes affichées, triées par nom.
    // Projection → renvoie des tableaux (pas d'objets) : aucun lazy loading possible.
    
    public function findForIndex(): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.id', 'f.name')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
