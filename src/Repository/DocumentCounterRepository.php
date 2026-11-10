<?php

namespace App\Repository;

use App\Entity\DocumentCounter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentCounter>
 */
class DocumentCounterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentCounter::class);
    }

    // Le numéro suivant du mois : BHD-2026-11-001, puis 002… Le compteur repart à 001 chaque mois.
    // L'écriture verrouille la ligne du compteur jusqu'à la fin de la transaction : si deux vendeurs
    // enregistrent en même temps, le second attend le premier et reçoit le numéro d'après.
    // Dans la transaction qui enregistre le document : s'il échoue, le compteur revient en arrière.
    public function next(string $type, \DateTimeInterface $date): string
    {
        $connection = $this->getEntityManager()->getConnection();

        if (!$connection->isTransactionActive()) {
            throw new \LogicException('Un numéro de document se prend dans la transaction qui enregistre le document.');
        }

        $prefix = $type . '-' . $date->format('Y-m');
        $connection->executeStatement(
            'INSERT INTO document_counter (prefix, last_number) VALUES (:prefix, 1) ON DUPLICATE KEY UPDATE last_number = last_number + 1',
            ['prefix' => $prefix],
        );
        $number = $connection->fetchOne('SELECT last_number FROM document_counter WHERE prefix = :prefix', ['prefix' => $prefix]);

        return sprintf('%s-%03d', $prefix, $number);
    }
}
