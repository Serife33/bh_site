<?php

namespace App\Repository;

use App\Entity\Media;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    use PositionsTrait;   // updatePositions() : la grille de photos d'un produit

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    // La première place libre dans la galerie d'un produit : une photo neuve se range à la fin.
    public function nextPosition(Product $product): int
    {
        $max = $this->createQueryBuilder('m')
            ->select('MAX(m.position)')
            ->andWhere('m.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;   // produit sans photo : MAX vaut null, la première place est 1
    }
}
