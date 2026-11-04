<?php

namespace App\Command;

use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\ProductRepository;
use App\Service\CsvReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * Regroupe les fiches d'un même modèle, une par taille, en une seule fiche à variantes, d'après la liste validée
 * (data/regroupement-lits.csv, faite par app:liste-lits) : une ligne par fiche, avec la fiche gardée de son groupe.
 * La commande ne calcule aucun prix : chaque variante reprend le prix initial, le prix actuel et le stock de la fiche
 * de sa taille, qui doivent être ceux de la liste.
 *
 * La fiche gardée prend le nom, l'adresse, le titre et la description Google de la liste, perd sa taille dans sa description,
 * et reçoit les photos des autres fiches qu'elle n'a pas déjà (même contenu de fichier, même vidéo) ; les deux photos
 * choisies dans la liste ouvrent sa galerie. Chaque variante retient l'adresse de sa fiche pour la redirection 301.
 * Les autres fiches sont masquées ; --supprime-anciennes les supprime, une fois le regroupement vérifié.
 *
 * Relançable : un groupe dont la fiche gardée a déjà des variantes n'est pas refait, il est comparé à la liste.
 * Tout ou rien : tout est contrôlé avant d'écrire, puis la base est écrite en une seule transaction.
 */
#[AsCommand(name: 'app:regroupe-fiches', description: 'Regroupe les fiches d\'un même modèle, une par taille, en une fiche à variantes')]
final class GroupProductsCommand
{
    // Les colonnes de la nouvelle fiche, remplies sur la ligne de la fiche gardée seulement
    private const NEW_PRODUCT = ['nouveau_nom', 'nouvelle_adresse', 'titre_google', 'description_google', 'photo_1', 'photo_2'];

    // Les colonnes utiles de la liste, une ligne par fiche. Les autres (nom, photos) aident seulement à la relire.
    private const COLUMNS = ['fiche_gardee', 'id', 'adresse', 'taille', 'prix_initial', 'prix_actuel', 'stock', ...self::NEW_PRODUCT];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CsvReader $csv,
        private readonly ProductRepository $productRepository,
        private readonly StorageInterface $storage,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('La liste validée')] string $fichier,
        #[Option('Affiche ce qui serait fait, sans rien écrire')] bool $dryRun = false,
        #[Option('Supprime les anciennes fiches, si tous les groupes sont déjà regroupés et conformes à la liste')] bool $supprimeAnciennes = false,
    ): int {
        $groups = [];
        foreach ($this->csv->read($fichier, self::COLUMNS) as $row) {
            $groups[$row['fiche_gardee']][] = $row;
        }

        $ids = array_column(array_merge(...array_values($groups)), 'id');
        if ($twice = array_unique(array_diff_assoc($ids, array_unique($ids)))) {
            throw new \RuntimeException('Fiches présentes sur deux lignes : ' . implode(', ', $twice) . '.');
        }
        $products = $this->productRepository->findWithMediaByIds($ids);

        $newSlugs = [];
        $old = [];   // les anciennes fiches des groupes déjà regroupés, pour --supprime-anciennes
        $count = ['Fiches regroupées' => 0, 'Variantes créées' => 0, 'Photos ajoutées' => 0, 'Fiches masquées' => 0, 'Groupes déjà regroupés' => 0, 'Différences avec la liste' => 0];

        foreach ($groups as $keptId => $rows) {
            $kept = $products[$keptId] ?? throw new \RuntimeException("Fiche gardée $keptId introuvable.");
            $keptRow = array_find($rows, static fn (array $row) => $row['id'] === (string) $keptId)
                ?? throw new \RuntimeException("Fiche gardée $keptId : elle n'a pas sa propre ligne dans son groupe.");

            // Déjà regroupé : chaque taille est comparée à la ligne de sa fiche, et chaque ancienne fiche doit être restée masquée
            if (!$kept->getVariants()->isEmpty()) {
                ++$count['Groupes déjà regroupés'];
                $io->text("= {$kept->getName()} : déjà regroupé, comparé à la liste");
                foreach ($rows as $row) {
                    $variant = $kept->getVariants()->findFirst(static fn (int $key, ProductVariant $variant) => $variant->getLegacySlug() === $row['adresse']);
                    $differences = $variant ? $this->differences($row, $variant) : ['taille absente de la fiche regroupée'];

                    $product = $products[$row['id']] ?? null;   // une ancienne fiche déjà supprimée n'a plus rien à contrôler
                    if ($product !== null && $product !== $kept) {
                        if ($product->isActive()) {
                            $differences[] = 'ancienne fiche de nouveau visible';
                        }
                        $old[] = $product;
                    }

                    foreach ($differences as $difference) {
                        ++$count['Différences avec la liste'];
                        $io->text("    ! {$row['taille']} ({$row['adresse']}) : $difference");
                    }
                }

                continue;
            }

            // Une variante par fiche, avec ses prix et son stock tels quels, dans l'ordre de la liste
            foreach ($rows as $index => $row) {
                $product = $products[$row['id']] ?? throw new \RuntimeException("Fiche {$row['id']} introuvable.");
                $isKept = $product === $kept;

                if ($product->getSlug() !== $row['adresse']) {
                    throw new \RuntimeException("Fiche {$row['id']} : son adresse est « {$product->getSlug()} », pas « {$row['adresse']} ».");
                }
                // Masquée, elle redeviendrait une taille en vente
                if (!$product->isActive()) {
                    throw new \RuntimeException("{$product->getName()} est masquée : à retirer de la liste, ou à remettre en ligne avant.");
                }
                if (!preg_match('/^\d{2,3}x\d{2,3}$/', $row['taille'])) {
                    throw new \RuntimeException("Fiche {$row['id']} : taille « {$row['taille']} » à écrire comme 140x190.");
                }
                if ($differences = $this->differences($row, $product)) {
                    throw new \RuntimeException("{$product->getName()} a changé depuis la liste : " . implode(', ', $differences) . '.');
                }
                if (!$isKept && !$product->getVariants()->isEmpty()) {
                    throw new \RuntimeException("{$product->getName()} a déjà des variantes : à regrouper à la main.");
                }
                // Les colonnes de la nouvelle fiche se lisent sur la ligne de la fiche gardée, qui a au moins un nom et une adresse
                $filled = array_filter(array_intersect_key($row, array_flip(self::NEW_PRODUCT)), static fn (string $value) => $value !== '');
                if ($isKept ? !isset($filled['nouveau_nom'], $filled['nouvelle_adresse']) : $filled !== []) {
                    throw new \RuntimeException("Fiche {$row['id']} : nouveau nom et nouvelle adresse à remplir sur la ligne de la fiche gardée ; les colonnes de la nouvelle fiche restent vides sur les autres lignes.");
                }

                $kept->addVariant((new ProductVariant())
                    ->setSize($row['taille'])
                    ->setInitialPrice($product->getInitialPrice())
                    ->setActualPrice($product->getActualPrice())
                    ->setStock($product->getStock())
                    ->setPosition($index + 1)
                    ->setLegacySlug($product->getSlug()));
            }

            // Les photos des autres fiches qui manquent à la fiche gardée, à la suite des siennes ; les autres fiches sont masquées
            $gallery = $kept->getMedia()->toArray();
            usort($gallery, static fn (Media $a, Media $b) => $a->getPosition() <=> $b->getPosition());
            $known = array_map($this->fingerprint(...), $gallery);
            $added = 0;
            foreach ($rows as $row) {
                $product = $products[$row['id']];
                if ($product === $kept) {
                    continue;
                }
                foreach ($product->getMedia() as $media) {
                    if (!in_array($fingerprint = $this->fingerprint($media), $known, true)) {
                        $known[] = $fingerprint;
                        // Pas de removeMedium() sur l'ancienne fiche : orphanRemoval supprimerait la photo
                        $kept->addMedium($media);
                        $gallery[] = $media;
                        ++$added;
                    }
                }
                $product->setIsActive(false);
                ++$count['Fiches masquées'];
            }

            // Les deux photos choisies ouvrent la galerie (le lit de trois quarts avec son matelas, puis le coffre ouvert), les autres suivent
            $first = array_values(array_filter([$keptRow['photo_1'], $keptRow['photo_2']]));
            if ($missing = array_diff($first, array_map(static fn (Media $media) => $media->getUrl(), $gallery))) {
                throw new \RuntimeException("{$kept->getName()} : photo choisie absente de la fiche : " . implode(', ', $missing) . '.');
            }
            $position = count($first);
            foreach ($gallery as $media) {
                $rank = array_search($media->getUrl(), $first, true);
                $media->setPosition($rank === false ? ++$position : $rank + 1);
            }

            $kept->setName($keptRow['nouveau_nom'])
                ->setSlug($keptRow['nouvelle_adresse'])
                ->setMetaTitle($keptRow['titre_google'] ?: null)
                ->setMetaDescription($keptRow['description_google'] ?: null)
                ->setDescription(ProductVariant::withoutSize($kept->getDescription()));

            // Refusé dès l'à-blanc : en base, l'adresse unique ferait échouer tout le regroupement
            if (isset($newSlugs[$kept->getSlug()])) {
                throw new \RuntimeException("Deux fiches regroupées auraient l'adresse « {$kept->getSlug()} ».");
            }
            $newSlugs[$kept->getSlug()] = true;

            // Prix « à partir de » et stock calculés tout de suite, pour que la validation les voie
            $kept->refreshFromVariants();
            if (count($violations = $this->validator->validate($kept)) > 0) {
                throw new \RuntimeException("{$kept->getName()} :\n$violations");
            }

            ++$count['Fiches regroupées'];
            $count['Variantes créées'] += $kept->getVariants()->count();
            $count['Photos ajoutées'] += $added;

            $io->text(sprintf('+ %s (%s), fiche %d gardée : %d tailles, %d photo(s) ajoutée(s), %d fiche(s) masquée(s)',
                $kept->getName(), $kept->getSlug(), $kept->getId(), $kept->getVariants()->count(), $added, count($rows) - 1));
            foreach ($kept->getVariants() as $variant) {
                $io->text(sprintf('    %s : %s €, prix initial %s €, stock %d, depuis %s',
                    $variant->getSize(), $variant->getActualPrice(), $variant->getInitialPrice(), $variant->getStock(), $variant->getLegacySlug()));
            }
            $io->text("    titre Google : {$kept->getMetaTitle()}");
            $io->text("    description Google : {$kept->getMetaDescription()}");
            $io->text('    photos 1 et 2 : ' . implode(', ', $first));
        }

        // Les anciennes fiches partent seulement quand tout est regroupé et conforme. Leurs photos (des copies) et leurs
        // lignes de l'accueil partent avec elles ; leurs redirections restent, portées par les variantes.
        if ($supprimeAnciennes) {
            if ($count['Fiches regroupées'] > 0 || $count['Différences avec la liste'] > 0) {
                throw new \RuntimeException('Suppression refusée : tous les groupes doivent être déjà regroupés, sans différence avec la liste.');
            }
            foreach ($old as $product) {
                $this->em->remove($product);
                $io->text("- {$product->getName()} ({$product->getSlug()}) : supprimée");
            }
            $count['Anciennes fiches supprimées'] = count($old);
        }

        if (!$dryRun) {
            $this->em->flush();   // une seule transaction : les variantes suivent leur fiche
        }

        $io->newLine();
        $io->definitionList(...array_chunk($count, 1, true));
        $dryRun ? $io->note('À blanc : rien n\'a été écrit.') : $io->success('Regroupement terminé.');

        if ($count['Différences avec la liste'] > 0) {
            $io->warning('Des tailles ou des anciennes fiches ne correspondent plus à la liste : voir les lignes « ! ».');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Ce qui diffère entre une ligne de la liste et la fiche ou la variante de sa taille.
     *
     * @return list<string>
     */
    private function differences(array $row, Product|ProductVariant $item): array
    {
        $differences = [];
        foreach (['prix_initial' => $item->getInitialPrice(), 'prix_actuel' => $item->getActualPrice(), 'stock' => $item->getStock()] as $column => $value) {
            if ((float) $this->csv->decimal($row, $column, "fiche {$row['id']}") !== (float) $value) {
                $differences[] = "$column $value en base, {$row[$column]} dans la liste";
            }
        }

        return $differences;
    }

    // Deux photos sont la même si leurs fichiers ont le même contenu ; deux vidéos, si elles ont le même identifiant YouTube
    private function fingerprint(Media $media): string
    {
        if ($media->isVideo()) {
            return 'youtube:' . $media->getVideoId();
        }

        $path = $this->storage->resolvePath($media, 'imageFile');

        return is_file($path)
            ? hash_file('sha256', $path)
            : throw new \RuntimeException("Photo absente du disque : {$media->getUrl()} ({$media->getProduct()->getName()}).");
    }
}
