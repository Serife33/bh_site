<?php

namespace App\Repository;

use App\Entity\Announcement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Announcement>
 */
class AnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announcement::class);
    }

    // Liste pour l'index admin : tous les messages, dans l'ordre du bandeau
    public function findForIndex(): array
    {
        return $this->createBandQueryBuilder()
            ->getQuery()
            ->getResult();
    }

    // Le bandeau d'un jour donné : les messages visibles, permanents ou dans leur période (jours de début et de fin inclus).
    // Le type DATE ne garde que le jour de $day, dans son propre fuseau.
    public function findVisibleOn(\DateTimeImmutable $day): array
    {
        return $this->createBandQueryBuilder()
            ->andWhere('a.isVisible = true')
            ->andWhere('a.startDate IS NULL OR a.startDate <= :day')
            ->andWhere('a.endDate IS NULL OR a.endDate >= :day')
            ->setParameter('day', $day, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();
    }

    // L'ordre du bandeau : les promos d'abord, puis par position
    private function createBandQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.isPromo', 'DESC')
            ->addOrderBy('a.position', 'ASC')
            ->addOrderBy('a.id', 'ASC');
    }
}
