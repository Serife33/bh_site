<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;
use Psr\Log\LoggerInterface;

/**
 * Normalise CHAQUE image déposée, quelle que soit l'entité (Media, Category, FabricColor…).
 * Générique : il travaille à partir du mapping Vich, donc un seul listener
 * couvre tous les mappings, présents et à venir.
 *
 * Objectif : l'administratrice n'a RIEN à préparer avant d'envoyer une photo.
 *   1. Applique l'orientation EXIF puis la neutralise  → plus de photos couchées
 *   2. Convertit le HEIC (iPhone) en JPEG              → GD et les navigateurs ne le lisent pas
 *   3. Réduit à 2500 px max                            → le plus grand filtre sort en 1600 px
 *   4. Convertit en sRGB                               → sinon les couleurs virent au terne
 *   5. Retire les métadonnées                          → allège, et supprime les données GPS
 */
#[AsEventListener(event: Events::POST_UPLOAD)]
final class ImageNormalizerListener
{
    private const MAX_SIZE = 2500;

    public function __construct(
        private EntityManagerInterface $em, 
        private LoggerInterface $logger,
        )
    {
    }

    public function __invoke(Event $event): void
    {
        if (!extension_loaded('imagick')) {
            return;
        }

        $objet = $event->getObject();
        $mapping = $event->getMapping();
        $nomFichier = $mapping->getFileName($objet);

        if ($nomFichier === null) {
            return;
        }

        $dossier = rtrim($mapping->getUploadDestination(), '/');
        $chemin = $dossier . '/' . $nomFichier;

        if (!is_file($chemin)) {
            return;
        }

        try {
            // Plafonne la mémoire d'ImageMagick : au-delà, il bascule sur le disque
            // au lieu de saturer la RAM (une photo 51 Mpx = ~400 Mo décompressée).
            \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);

            $image = new \Imagick($chemin);

            // 1. Orientation : on la fixe physiquement, puis on remet le drapeau à zéro.
            //    Le nom de la méthode a changé selon les versions d'Imagick → on teste.
            if (method_exists($image, 'autoOrient')) {
                $image->autoOrient();
            } elseif (method_exists($image, 'autoOrientImage')) {
                $image->autoOrientImage();
            }
            $image->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);

            // 2. HEIC / HEIF → JPEG
            $format = strtolower($image->getImageFormat());
            $nouveauNom = $nomFichier;

            if (in_array($format, ['heic', 'heif'], true)) {
                $image->setImageFormat('jpeg');
                $nouveauNom = preg_replace('/\.[^.]+$/', '.jpg', $nomFichier);
            }

            // 3. Réduction — le dernier paramètre (true) = bestFit : proportions conservées
            if ($image->getImageWidth() > self::MAX_SIZE || $image->getImageHeight() > self::MAX_SIZE) {
                $image->resizeImage(self::MAX_SIZE, self::MAX_SIZE, \Imagick::FILTER_LANCZOS, 1, true);
            }

            // 4. Espace colorimétrique : les photos fournisseur arrivent souvent en Adobe RGB.
            $image->transformImageColorspace(\Imagick::COLORSPACE_SRGB);

            // 5. Métadonnées devenues inutiles (l'orientation est déjà appliquée)
            $image->stripImage();
            $image->setImageCompressionQuality(85);

            $nouveauChemin = $dossier . '/' . $nouveauNom;
            $image->writeImage($nouveauChemin);
            $image->clear();

            // Le HEIC a changé d'extension : on supprime l'ancien fichier
            // et on met à jour le nom stocké en base.
            if ($nouveauNom !== $nomFichier) {
                @unlink($chemin);
                $mapping->setFileName($objet, $nouveauNom);
                $this->rafraichirDoctrine($objet);
            }
        } catch (\Throwable $e) {
            // Une image non traitable ne doit JAMAIS bloquer l'enregistrement,
            // mais l'incident doit être traçable.
            $this->logger->warning('Normalisation impossible : {message}', [
                'message' => $e->getMessage(),
                'fichier' => $chemin,
            ]);
        }
    }

    /**
     * Lors d'un REMPLACEMENT de photo, Doctrine a déjà calculé ce qu'il va écrire
     * avant que ce listener ne tourne. Il faut donc lui demander de recalculer,
     * sinon le nouveau nom de fichier ne partirait pas en base.
     */
    private function rafraichirDoctrine(object $objet): void
    {
        try {
            $uow = $this->em->getUnitOfWork();
            $uow->recomputeSingleEntityChangeSet(
                $this->em->getClassMetadata($objet::class),
                $objet
            );
        } catch (\Throwable $e) {
            // Cas de la création : rien à recalculer.
        }
    }
}