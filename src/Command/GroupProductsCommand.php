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
 * Regroupe les fiches d'un même modèle, une par taille, en une seule fiche à variantes, d'après un fichier de correspondance :
 * une ligne par fiche, avec la fiche gardée de son groupe (data/regroupement-lits.csv).
 * La commande ne calcule aucun prix : chaque variante reprend le prix initial, le prix actuel et le stock de la fiche
 * de sa taille, qui doivent être ceux de la liste validée.
 *
 * La fiche gardée prend son nouveau nom et sa nouvelle adresse, perd sa taille dans ses textes, et reçoit les photos
 * des autres fiches qu'elle n'a pas déjà (même contenu de fichier, même vidéo). Chaque variante retient l'adresse
 * de sa fiche pour la redirection 301. Les autres fiches sont masquées : rien n'est supprimé.
 *
 * Relançable : un groupe dont la fiche gardée a déjà des variantes n'est pas refait, ses variantes sont comparées à la liste.
 * Tout ou rien : tout est contrôlé avant d'écrire, puis la base est écrite en une seule transaction.
 */
#[AsCommand(name: 'app:regroupe-fiches', description: 'Regroupe les fiches d\'un même modèle, une par taille, en une fiche à variantes')]
final class GroupProductsCommand
{
    // Les colonnes utiles du fichier, une ligne par fiche. Les autres (nom, photos) aident seulement à le relire.
    private const COLUMNS = ['fiche_gardee', 'id', 'adresse', 'taille', 'prix_initial', 'prix_actuel', 'stock', 'nouveau_nom', 'nouvelle_adresse'];

    // Une taille dans un texte, avec l'espace qui la précède : « 140x190 », « 140X200 », « 180*200 »
    private const SIZE = '\s*(?<!\d)\d{2,3}\s*[x×X*]\s*\d{2,3}(?!\d)';

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
        #[Argument('Le fichier de correspondance, validé')] string $fichier,
        #[Option('Affiche ce qui serait fait, sans rien écrire')] bool $dryRun = false,
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
        $count = ['Fiches regroupées' => 0, 'Variantes créées' => 0, 'Photos ajoutées' => 0, 'Fiches masquées' => 0, 'Groupes déjà regroupés' => 0, 'Différences avec la liste' => 0];

        foreach ($groups as $keptId => $rows) {
            $kept = $products[$keptId] ?? throw new \RuntimeException("Fiche gardée $keptId introuvable.");
            $keptRow = array_find($rows, static fn (array $row) => $row['id'] === (string) $keptId)
                ?? throw new \RuntimeException("Fiche gardée $keptId : elle n'a pas sa propre ligne dans son groupe.");

            // Déjà regroupé : chaque variante est comparée à la ligne de sa fiche
            if (!$kept->getVariants()->isEmpty()) {
                ++$count['Groupes déjà regroupés'];
                $io->text("= {$kept->getName()} : déjà regroupé, comparé à la liste");
                foreach ($rows as $row) {
                    $variant = $kept->getVariants()->findFirst(static fn (int $key, ProductVariant $variant) => $variant->getLegacySlug() === $row['adresse']);
                    foreach ($variant ? $this->differences($row, $variant) : ['variante absente'] as $difference) {
                        ++$count['Différences avec la liste'];
                        $io->text("    ! {$row['taille']} ({$row['adresse']}) : $difference");
                    }
                }

                continue;
            }

            // Une variante par fiche, avec ses prix et son stock tels quels, dans l'ordre du fichier
            foreach ($rows as $index => $row) {
                $product = $products[$row['id']] ?? throw new \RuntimeException("Fiche {$row['id']} introuvable.");

                if ($product->getSlug() !== $row['adresse']) {
                    throw new \RuntimeException("Fiche {$row['id']} : son adresse est « {$product->getSlug()} », pas « {$row['adresse']} ».");
                }
                if (!preg_match('/^\d{2,3}x\d{2,3}$/', $row['taille'])) {
                    throw new \RuntimeException("Fiche {$row['id']} : taille « {$row['taille']} » à écrire comme 140x190.");
                }
                if ($differences = $this->differences($row, $product)) {
                    throw new \RuntimeException("{$product->getName()} a changé depuis la liste : " . implode(', ', $differences) . '.');
                }
                if ($product !== $kept && !$product->getVariants()->isEmpty()) {
                    throw new \RuntimeException("{$product->getName()} a déjà des variantes : à regrouper à la main.");
                }
                // Le nouveau nom et la nouvelle adresse se lisent sur la ligne de la fiche gardée, et seulement là
                if ([$row['nouveau_nom'] !== '', $row['nouvelle_adresse'] !== ''] !== [$product === $kept, $product === $kept]) {
                    throw new \RuntimeException("Fiche {$row['id']} : nouveau nom et nouvelle adresse sont à remplir sur la ligne de la fiche gardée, et vides ailleurs.");
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
            $known = array_map($this->fingerprint(...), $kept->getMedia()->toArray());
            $position = max([0, ...$kept->getMedia()->map(static fn (Media $media) => $media->getPosition())->toArray()]);
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
                        $kept->addMedium($media->setPosition(++$position));
                        ++$added;
                    }
                }
                $product->setIsActive(false);
                ++$count['Fiches masquées'];
            }

            // La fiche regroupée vend toutes les tailles : la sienne disparaît de ses textes
            $kept->setName($keptRow['nouveau_nom'])
                ->setSlug($keptRow['nouvelle_adresse'])
                ->setMetaTitle($this->withoutSize($kept->getMetaTitle()))
                ->setMetaDescription($this->withoutSize($kept->getMetaDescription()))
                ->setDescription($this->withoutSize($kept->getDescription()));

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
        }

        if (!$dryRun) {
            $this->em->flush();   // une seule transaction : les variantes suivent leur fiche
        }

        $io->newLine();
        $io->definitionList(...array_chunk($count, 1, true));
        $dryRun ? $io->note('À blanc : rien n\'a été écrit.') : $io->success('Regroupement terminé.');

        if ($count['Différences avec la liste'] > 0) {
            $io->warning('Des variantes ne correspondent plus à la liste : voir les lignes « ! ».');

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

    // Collée au mot suivant (« 160x200à cadre »), la taille laisse une espace à sa place
    private function withoutSize(?string $text): ?string
    {
        return $text === null ? null : preg_replace(['/' . self::SIZE . '(?=\p{L})/u', '/' . self::SIZE . '/u'], [' ', ''], $text);
    }
}
