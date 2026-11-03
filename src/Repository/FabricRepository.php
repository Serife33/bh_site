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

    
    // Liste pour l'index admin : objets complets + couleurs chargées en une seule requête.
    // leftJoin  : un tissu sans couleur doit rester visible (un INNER le ferait disparaître).
    // addSelect : sans lui la jointure ne rapatrie rien → N+1 au comptage des couleurs.
    public function findForIndex(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.fabricColors', 'fc')
            ->addSelect('fc')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // Les matières d'un fournisseur : l'import ne recrée pas celles déjà en base
    public function findBySupplier(string $supplier): array
    {
        return $this->findBy(['supplier' => $supplier]);
    }
}
