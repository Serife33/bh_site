<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Quote;
use App\Enum\InvoiceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    // La facture d'un devis pour ce type, brouillon ou émise
    public function findOneForQuote(Quote $quote, InvoiceType $type = InvoiceType::Deposit): ?Invoice
    {
        return $this->findOneBy(['quote' => $quote, 'type' => $type]);
    }

    // Relit le document et verrouille sa ligne jusqu'à la fin de la transaction : une autre requête
    // (double clic, autre onglet) a pu créer, émettre ou supprimer une facture depuis son chargement
    public function lock(Invoice|Quote $document): void
    {
        $this->getEntityManager()->refresh($document, LockMode::PESSIMISTIC_WRITE);
    }
}
