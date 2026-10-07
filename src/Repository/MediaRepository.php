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

    // Enregistre un nouvel ordre : la 1re photo reçoit la position 1, la 2e la position 2…
    // UPDATE direct en une transaction : tout ou rien.
    public function updatePositions(array $ids): void
    {
        $requete = $this->createQueryBuilder('m')
            ->update()
            ->set('m.position', ':position')
            ->where('m.id = :id')
            ->getQuery();

        $this->getEntityManager()->wrapInTransaction(function () use ($requete, $ids) {
            foreach (array_values($ids) as $rang => $id) {
                $requete->setParameter('position', $rang + 1)
                        ->setParameter('id', $id)
                        ->execute();
            }
        });
    }
}
