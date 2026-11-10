<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Quote;
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

    // La facture d'acompte d'un devis, brouillon ou émise
    public function findOneForQuote(Quote $quote): ?Invoice
    {
        return $this->findOneBy(['quote' => $quote]);
    }

    // Relit la facture et verrouille sa ligne jusqu'à la fin de la transaction : une autre requête
    // (double clic, autre onglet) a pu l'émettre ou la supprimer depuis son chargement
    public function lock(Invoice $invoice): void
    {
        $this->getEntityManager()->refresh($invoice, LockMode::PESSIMISTIC_WRITE);
    }
}
