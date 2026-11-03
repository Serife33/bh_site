<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
