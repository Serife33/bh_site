<?php

namespace App\Repository;

use App\Entity\HomeSection;
use App\Enum\ProductModular;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomeSection>
 */
class HomeSectionRepository extends ServiceEntityRepository
{
    use PositionsTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomeSection::class);
    }

    // Liste de l'admin : toutes les sections, masquées comprises, dans l'ordre de l'accueil
    public function findForIndex(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.id', 'ASC')   // départage : deux positions égales sortent toujours dans le même ordre
            ->getQuery()
            ->getResult();
    }

    // Accueil : les sections visibles, chacune avec ses produits visibles et leurs photos, en une requête.
    // Jointure interne : une section sans produit visible n'est pas renvoyée. Les produits masqués manquent
    // donc à la collection chargée : ce résultat sert à l'affichage, jamais à une modification.
    public function findVisibleForHome(): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.items', 'i')
            ->join('i.product', 'p')
            ->leftJoin('p.media', 'm')
            ->addSelect('i', 'p', 'm')
            ->andWhere('s.isVisible = true')
            ->andWhere('p.isActive = true')
            ->andWhere('p.isModular != :module')   // un produit devenu module ne s'affiche plus que sur la fiche de son ensemble
            ->setParameter('module', ProductModular::Module)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('s.id', 'ASC')   // puis produits et photos dans leur ordre : Doctrine ajoute leurs #[OrderBy]
            ->getQuery()
            ->getResult();
    }

    // La première place libre : une section neuve se range à la fin de l'accueil
    public function nextPosition(): int
    {
        $max = $this->createQueryBuilder('s')
            ->select('MAX(s.position)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;   // aucune section : MAX vaut null, la première place est 1
    }
}
