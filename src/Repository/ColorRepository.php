<?php

namespace App\Repository;

use App\Entity\Color;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Color>
 */
class ColorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Color::class);
    }

    
    // Liste pour l'index admin : uniquement les colonnes affichées, triées par nom.
    // Projection → renvoie des tableaux (pas d'objets) : aucun lazy loading possible.
    
    public function findForIndex(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.id', 'c.name', 'c.hex') // SQL : SELECT id, name, hex FROM color
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult(); // tableaux bruts, pas d'objets Color
    }
}
