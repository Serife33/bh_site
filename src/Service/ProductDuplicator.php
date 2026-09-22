<?php

namespace App\Service;

use App\Entity\Media;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Duplique un produit : prépare une copie pré-remplie, puis recopie ses photos.
 * Pratique pour les variantes d'un même modèle (Genova 140, 160, 180…).
 */
final class ProductDuplicator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        // Même dossier que le mapping « product_media » de config/packages/vich_uploader.yaml
        #[Autowire('%kernel.project_dir%/public/uploads/products')]
        private readonly string $photosDir,
    ) {
    }

    /**
     * Copie en mémoire, pas encore enregistrée : tout le contenu de l'original,
     * sauf l'adresse (régénérée depuis le nouveau nom), le référencement et les photos.
     */
    public function prepareCopy(Product $original): Product
    {
        $copy = (new Product())
            ->setName($original->getName() . ' (copie)')
            ->setSlug('') // vide : Product::generateSlug() la recrée depuis le nom à l'enregistrement
            ->setDescription($original->getDescription())
            ->setDimension($original->getDimension())
            ->setInitialPrice($original->getInitialPrice())
            ->setActualPrice($original->getActualPrice())
            ->setStock($original->getStock())
            ->setIsCustomMade($original->isCustomMade())
            ->setIsModular($original->getIsModular())
            ->setSideLr($original->getSideLr())
            ->setLeadMinWeeks($original->getLeadMinWeeks())
            ->setLeadMaxWeeks($original->getLeadMaxWeeks())
            ->setPosition($original->getPosition())
            ->setIsActive($original->isActive())
            ->setCategory($original->getCategory())
            ->setFamily($original->getFamily());

        foreach ($original->getSubCategories() as $subCategory) {
            $copy->addSubCategory($subCategory);
        }
        foreach ($original->getFabrics() as $fabric) {
            $copy->addFabric($fabric);
        }
        foreach ($original->getColors() as $color) {
            $copy->addColor($color);
        }
        foreach ($original->getModules() as $module) {
            $copy->addModule($module);
        }

        return $copy;
    }

    /**
     * Recopie les photos de l'original sur la copie.
     * Chaque fichier est dupliqué sur le disque : supprimer une photo de l'un
     * n'efface jamais celle de l'autre.
     */
    public function copyPhotos(Product $original, Product $copy): void
    {
        foreach ($original->getMedia() as $media) {
            $source = $this->photosDir . '/' . $media->getUrl();

            // Fichier absent (en local sans les photos du serveur, par exemple) : on passe
            if (!is_file($source)) {
                continue;
            }

            $newName = pathinfo($media->getUrl(), PATHINFO_FILENAME)
                . '-' . bin2hex(random_bytes(4))
                . '.' . pathinfo($media->getUrl(), PATHINFO_EXTENSION);

            if (!copy($source, $this->photosDir . '/' . $newName)) {
                continue;
            }

            $photo = (new Media())
                ->setUrl($newName)
                ->setAlt($media->getAlt())
                ->setType($media->getType())
                ->setIsMain($media->isMain())
                ->setPosition($media->getPosition());

            $copy->addMedium($photo);
            $this->em->persist($photo);
        }
    }
}
