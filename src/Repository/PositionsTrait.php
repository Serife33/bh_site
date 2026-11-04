<?php

namespace App\Repository;

// Les listes rangées au glisser-déposer de l'admin : produits, photos, sections de l'accueil et leurs produits.
trait PositionsTrait
{
    // Enregistre un nouvel ordre : le 1er identifiant reçoit la position 1, le 2e la position 2…
    // UPDATE direct : seule la colonne position change, updatedAt ne bouge pas (le lastmod du sitemap reste juste).
    // Tout ou rien : si une requête échoue, aucune position n'est modifiée.
    public function updatePositions(array $ids): void
    {
        $requete = $this->createQueryBuilder('e')
            ->update()
            ->set('e.position', ':position')
            ->where('e.id = :id')
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
