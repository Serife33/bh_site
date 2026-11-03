<?php

namespace App\Repository;

use App\Entity\AdminUser;
use App\Entity\Customer;
use App\Entity\Quote;
use App\Enum\QuoteStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    // Liste des devis, du plus récent au plus ancien. Chaque filtre est optionnel : null = pas de filtre.
    // La recherche porte sur le numéro et sur le nom du client. Projection : seulement les colonnes affichées.
    public function findForAdminQuery(string $search, ?QuoteStatus $status, ?AdminUser $seller): Query
    {
        $qb = $this->createQueryBuilder('q')
            ->select('q.id', 'q.number', 'q.createdAt', 'q.validUntil', 'q.acceptedAt', 'q.status', 'q.total',
                'c.lastName', 'c.firstName', 's.email AS sellerEmail')
            ->join('q.customer', 'c')
            ->join('q.seller', 's')
            ->orderBy('q.createdAt', 'DESC')
            ->addOrderBy('q.id', 'DESC');   // départage pour la pagination

        if ($search !== '') {
            $qb->andWhere('q.number LIKE :search OR c.lastName LIKE :search OR c.firstName LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($status !== null) {
            $qb->andWhere('q.status = :status')
               ->setParameter('status', $status);
        }

        if ($seller !== null) {
            $qb->andWhere('q.seller = :seller')
               ->setParameter('seller', $seller);
        }

        return $qb->getQuery();   // non exécutée : le paginator ajoutera le LIMIT
    }

    // Un devis envoyé et pas signé à sa date de validité passe à Expiré.
    // Appelée à l'ouverture de la liste : pas de tâche planifiée à installer sur l'hébergement.
    public function expireOutdated(): void
    {
        $this->createQueryBuilder('q')
            ->update()
            ->set('q.status', ':expired')
            ->andWhere('q.status = :sent')
            ->andWhere('q.validUntil < :today')
            ->setParameter('expired', QuoteStatus::Expired)
            ->setParameter('sent', QuoteStatus::Sent)
            ->setParameter('today', new \DateTimeImmutable('today'), 'date_immutable')
            ->getQuery()
            ->execute();
    }

    // Un client qui a des devis ne se supprime pas : la base le refuserait (erreur 500)
    public function customerHasQuotes(Customer $customer): bool
    {
        return $this->count(['customer' => $customer]) > 0;
    }
}
