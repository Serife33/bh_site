<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    // Fichier client du back-office, filtré par la recherche (nom, prénom, téléphone ou e-mail).
    // Projection : seulement les colonnes affichées.
    public function findForAdminQuery(string $search): Query
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.id', 'c.lastName', 'c.firstName', 'c.phone', 'c.email', 'c.address')
            ->orderBy('c.lastName', 'ASC')
            ->addOrderBy('c.firstName', 'ASC')
            ->addOrderBy('c.id', 'ASC');   // départage : sans clé finale unique, la pagination oublie des lignes

        if ($search !== '') {
            $this->andWhereMatches($qb, $search);
        }

        return $qb->getQuery();   // non exécutée : le paginator ajoutera le LIMIT
    }

    // Le client d'un devis, cherché par le vendeur (QuoteController::customers) : les premiers par ordre alphabétique
    public function findForQuoteSearch(string $search, int $limit): array
    {
        return $this->andWhereMatches($this->createQueryBuilder('c'), $search)
            ->orderBy('c.lastName', 'ASC')
            ->addOrderBy('c.firstName', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // Le client d'un devis, reçu par son identifiant
    public function findForQuote(int $id): ?Customer
    {
        return $this->find($id);
    }

    // La recherche du fichier client, partagée par sa liste et le devis : nom, prénom, téléphone ou e-mail.
    // Les % et _ tapés sont cherchés tels quels, pas comme des jokers.
    private function andWhereMatches(QueryBuilder $qb, string $search): QueryBuilder
    {
        return $qb->andWhere('c.lastName LIKE :search OR c.firstName LIKE :search OR c.phone LIKE :search OR c.email LIKE :search')
            ->setParameter('search', '%' . addcslashes($search, '%_\\') . '%');
    }
}
