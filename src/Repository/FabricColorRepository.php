<?php

namespace App\Repository;

use App\Entity\FabricColor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FabricColor>
 */
class FabricColorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FabricColor::class);
    }

    public function findForIndex(): array
    {
        return $this->createQueryBuilder('fc')
            ->join('fc.fabric', 'f')
            ->addSelect('f')
            ->orderBy('f.name', 'ASC')
            ->addOrderBy('fc.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
