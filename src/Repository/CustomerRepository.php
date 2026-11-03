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
            $qb->andWhere('c.lastName LIKE :search OR c.firstName LIKE :search OR c.phone LIKE :search OR c.email LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery();   // non exécutée : le paginator ajoutera le LIMIT
    }

    // Les clients proposés dans le formulaire d'un devis, par ordre alphabétique.
    // shortcut: une liste déroulante de tout le fichier, à remplacer par une recherche au-delà de quelques centaines de clients
    public function createSortedQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.lastName', 'ASC')
            ->addOrderBy('c.firstName', 'ASC');
    }
}
