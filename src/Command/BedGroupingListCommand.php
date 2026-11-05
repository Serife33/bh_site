<?php

namespace App\Command;

use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\CsvReader;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fait la liste du regroupement des lits coffres, que lit ensuite app:regroupe-fiches. Elle se fait à partir de la base
 * configurée : pour la refaire d'après le site en ligne, on la lance sur une copie fraîche de la base en ligne.
 *
 * Les règles de Serife (4/11) : un groupe par modèle (la famille de la fiche) et par type de lit, une place (largeur
 * jusqu'à 120 cm) ou deux places ; la fiche gardée, dont la taille s'affiche en premier, est la 160x200 en deux places
 * et la 90x190 en une place ; le nouveau nom et le titre Google sont ceux de la fiche gardée sans sa taille (« Single »
 * reste, 5/11), l'adresse suit.
 *
 * Les choix déjà faits sur la ligne d'une fiche gardée (nom, adresse, textes Google, photos 1 et 2 reconnues à l'œil)
 * sont repris de la liste existante : la refaire ne les perd pas. Les faits (adresses, tailles, prix, stocks) sont relus.
 * Ce qui sort des règles est affiché et laissé hors de la liste : rien n'est deviné.
 */
#[AsCommand(name: 'app:liste-lits', description: 'Fait la liste du regroupement des lits coffres à partir de la base')]
final class BedGroupingListCommand
{
    // La taille de la fiche gardée, selon le type de lit
    private const KEPT_SIZE = ['deux places' => '160x200', 'une place' => '90x190'];

    private const FACTS = ['fiche_gardee', 'id', 'adresse', 'nom', 'taille', 'prix_initial', 'prix_actuel', 'stock', 'photos'];
    private const CHOICES = ['nouveau_nom', 'nouvelle_adresse', 'titre_google', 'description_google', 'photo_1', 'photo_2'];

    public function __construct(
        private readonly CsvReader $csv,
        private readonly CategoryRepository $categoryRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('La liste à écrire')] string $fichier = 'data/regroupement-lits.csv',
    ): int {
        $previous = [];
        if (is_file($fichier)) {
            foreach ($this->csv->read($fichier, ['fiche_gardee', 'id']) as $row) {
                if ($row['id'] === $row['fiche_gardee']) {
                    $previous[$row['id']] = $row;
                }
            }
        }

        // Les fiches rangées par modèle, type de lit et taille
        $category = $this->categoryRepository->findOneBySlug('literie') ?? throw new \RuntimeException('Catégorie « literie » introuvable.');
        $models = [];
        foreach ($this->productRepository->findByCategoryWithMedia($category) as $product) {
            $sizes = preg_match_all('/' . ProductVariant::SIZE_IN_TEXT . '/u', $product->getName(), $found, PREG_SET_ORDER);
            $doubt = match (true) {
                !$product->isActive() => 'masquée',
                !$product->getVariants()->isEmpty() => 'a déjà des variantes',
                $product->getFamily() === null => 'sans famille',
                $sizes !== 1 => 'taille illisible dans son nom',
                default => null,
            };
            if ($doubt !== null) {
                $io->text("! {$product->getName()} (n° {$product->getId()}) : $doubt, laissée hors de la liste");

                continue;
            }
            [, $width, $length] = $found[0];
            $models[$product->getFamily()->getName()][$width <= 120 ? 'une place' : 'deux places']["{$width}x$length"][] = $product;
        }
        ksort($models);

        $lines = [];
        $groupCount = 0;
        foreach ($models as $model => $types) {
            foreach (self::KEPT_SIZE as $type => $keptSize) {
                $bySize = $types[$type] ?? [];
                if ($bySize === []) {
                    continue;
                }
                if ($twice = array_keys(array_filter($bySize, static fn (array $products) => count($products) > 1))) {
                    $io->text("! $model $type : plusieurs fiches en " . implode(', ', $twice) . ', groupe laissé hors de la liste');

                    continue;
                }
                $kept = $bySize[$keptSize][0] ?? null;
                if ($kept === null) {
                    $io->text("! $model $type : pas de fiche $keptSize, groupe laissé hors de la liste");

                    continue;
                }

                // La taille de la fiche gardée d'abord, puis les autres de la plus petite à la plus grande
                uksort($bySize, static fn (string $a, string $b) => [$a !== $keptSize, ...sscanf($a, '%dx%d')] <=> [$b !== $keptSize, ...sscanf($b, '%dx%d')]);

                foreach ($bySize as $size => [$product]) {
                    $lines[] = [
                        'fiche_gardee' => $kept->getId(),
                        'id' => $product->getId(),
                        'adresse' => $product->getSlug(),
                        'nom' => $product->getName(),
                        'taille' => $size,
                        'prix_initial' => $product->getInitialPrice(),
                        'prix_actuel' => $product->getActualPrice(),
                        'stock' => $product->getStock(),
                        'photos' => $product->getMedia()->count(),
                        ...($product === $kept ? $this->choices($io, $kept, $previous[$kept->getId()] ?? []) : array_fill_keys(self::CHOICES, '')),
                    ];
                }
                ++$groupCount;
            }
        }

        $file = new \SplFileObject($fichier, 'w');
        $file->fwrite("\xEF\xBB\xBF");   // marque UTF-8 : Excel et Numbers ouvrent la liste avec ses accents
        foreach ([[...self::FACTS, ...self::CHOICES], ...$lines] as $line) {
            $file->fputcsv($line, ';', '"', '');
        }

        $io->success(sprintf('%d fiches en %d groupes, liste écrite dans %s. Ce qui a changé : git diff %s', count($lines), $groupCount, $fichier, $fichier));

        return Command::SUCCESS;
    }

    // Les colonnes de la nouvelle fiche : calculées, sauf les choix déjà faits dans la liste existante
    private function choices(SymfonyStyle $io, Product $kept, array $previous): array
    {
        $name = $this->oneLine(ProductVariant::withoutSize($kept->getName()));
        $photos = array_values(array_filter($kept->getMedia()->toArray(), static fn (Media $media) => $media->isPhoto()));
        usort($photos, static fn (Media $a, Media $b) => $a->getPosition() <=> $b->getPosition());
        $urls = array_map(static fn (Media $media) => $media->getUrl(), $photos);

        $computed = [
            'nouveau_nom' => $name,
            'nouvelle_adresse' => Product::slugFrom($name),
            'titre_google' => $this->oneLine(ProductVariant::withoutSize($kept->getMetaTitle())),
            'description_google' => $this->oneLine(ProductVariant::withoutSize($kept->getMetaDescription())),
            'photo_1' => $urls[0] ?? '',
            'photo_2' => $urls[1] ?? '',
        ];
        $choices = [...$computed, ...array_filter(array_intersect_key($previous, $computed), static fn (string $value) => $value !== '')];

        foreach (['photo_1', 'photo_2'] as $column) {
            if (!in_array($choices[$column], [...$urls, ''], true)) {
                $io->text("! {$kept->getName()} : $column « {$choices[$column]} » n'est plus sur la fiche, remplacée par « {$computed[$column]} », à revoir");
                $choices[$column] = $computed[$column];
            }
        }

        return $choices;
    }

    private function oneLine(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    }
}
