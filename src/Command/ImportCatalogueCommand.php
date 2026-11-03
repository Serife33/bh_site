<?php

namespace App\Command;

use App\Entity\Fabric;
use App\Entity\FabricColor;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductModular;
use App\Enum\ProductSide;
use App\Repository\CategoryRepository;
use App\Repository\FabricRepository;
use App\Repository\ProductRepository;
use App\Repository\SubCategoryRepository;
use App\Service\CsvReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

/**
 * Crée les produits d'un catalogue fournisseur à partir de son tableur de vente : un produit par modèle,
 * avec ses photos et une variante par ligne (taille, matière, prix). La commande ne calcule aucun prix.
 *
 * Le tableur est rangé dans le dossier de l'extraction, qui fournit le reste :
 * modeles.csv (nom, notes, photos), gammes.csv (finitions et vignettes), photos/ et finitions/.
 *
 * Relançable : un produit dont l'adresse existe déjà, une matière déjà en base ne sont pas recréés.
 * Tout ou rien : la moindre erreur arrête l'import avant toute écriture.
 */
#[AsCommand(name: 'app:import-catalogue', description: 'Importe un catalogue fournisseur depuis son tableur de vente')]
final class ImportCatalogueCommand
{
    // Les colonnes du tableur de vente, une ligne par variante. Les autres (une note, par exemple) sont ignorées.
    private const COLUMNS = ['id_page', 'nom_fournisseur', 'categorie', 'sous_categories', 'taille', 'gamme', 'matiere', 'prix_ttc', 'prix_barre_ttc'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CsvReader $csv,
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly SubCategoryRepository $subCategoryRepository,
        private readonly FabricRepository $fabricRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Le tableur de vente, rangé dans le dossier de l\'extraction')] string $tableur,
        #[Argument('Le fournisseur, enregistré sur les matières créées (ex. Masami)')] string $fournisseur,
        #[Option('Affiche ce qui serait créé, sans rien écrire')] bool $dryRun = false,
    ): int {
        $dir = dirname($tableur);

        // Un modèle est désigné par sa page et son nom fournisseur : deux modèles partagent parfois la même page.
        $models = [];
        foreach ($this->csv->read("$dir/modeles.csv", ['id_page', 'nom_fournisseur', 'nom_propose_fr', 'notes_fr', 'fichier_photo', 'autres_photos']) as $model) {
            $models[$model['id_page'] . ';' . $model['nom_fournisseur']] = $model;
        }

        $finishes = [];
        foreach ($this->csv->read("$dir/gammes.csv", ['gamme', 'finition', 'fichier_vignette']) as $finish) {
            $finishes[$finish['gamme']][] = $finish;
        }

        $sheet = $this->csv->read($tableur, self::COLUMNS);
        $rowsByModel = [];
        foreach ($sheet as $row) {
            $rowsByModel[$row['id_page'] . ';' . $row['nom_fournisseur']][] = $row;
        }

        $count = ['Produits' => 0, 'Variantes' => 0, 'Matières' => 0, 'Coloris' => 0, 'Photos' => 0, 'Produits déjà en base, ignorés' => 0];
        $new = [];   // tout ce qui sera enregistré, d'un coup, à la fin

        // 1. Les matières, désignées par leur libellé dans l'admin : « Céramique (Premium) ».
        // Une matière déjà en base garde ses coloris tels quels : ils ont pu être renommés dans l'admin.
        $fabrics = [];
        foreach ($this->fabricRepository->findBy(['supplier' => $fournisseur]) as $fabric) {
            $fabrics[(string) $fabric] = $fabric;
        }

        foreach ($sheet as $row) {
            if ($row['matiere'] === '' || isset($fabrics[$row['matiere']])) {
                continue;
            }

            $fabric = $fabrics[$row['matiere']] = $this->newFabric($row['matiere'], $fournisseur, $finishes[$row['gamme']] ?? [], $dir);
            $new = [...$new, $fabric, ...$fabric->getFabricColors()];
            ++$count['Matières'];
            $count['Coloris'] += $fabric->getFabricColors()->count();

            $io->text(sprintf('+ matière %s, %d coloris', $row['matiere'], $fabric->getFabricColors()->count()));
        }

        // 2. Les produits, un par modèle
        $positions = [];
        foreach ($rowsByModel as $key => $rows) {
            $model = $models[$key] ?? throw new \RuntimeException("Modèle absent de modeles.csv : $key.");
            $name = $model['nom_propose_fr'];
            $slug = (string) (new AsciiSlugger())->slug($name)->lower();   // la règle de Product::generateSlug()

            if ($this->productRepository->findOneBy(['slug' => $slug]) !== null) {
                ++$count['Produits déjà en base, ignorés'];
                $io->text("= $name : déjà en base, ignoré");

                continue;
            }

            $category = $this->categoryRepository->findOneBy(['name' => $rows[0]['categorie']])
                ?? throw new \RuntimeException("Catégorie inconnue : {$rows[0]['categorie']}.");

            // Masqué : textes, photos et prix sont à relire dans l'admin avant de le publier
            $product = (new Product())
                ->setName($name)
                ->setSlug($slug)
                ->setDescription($model['notes_fr'] ?: null)
                ->setCategory($category)
                ->setIsCustomMade(false)
                ->setIsModular(ProductModular::No)
                ->setSideLr(ProductSide::None)
                ->setIsActive(false);

            // Rangé à la fin de sa catégorie, à la suite des produits de cet import
            $positions[$category->getId()] ??= $this->productRepository->nextPosition($category);
            $product->setPosition($positions[$category->getId()]++);

            foreach (array_filter(array_map(trim(...), explode(',', $rows[0]['sous_categories']))) as $subCategoryName) {
                $product->addSubCategory($this->subCategoryRepository->findOneBy(['name' => $subCategoryName])
                    ?? throw new \RuntimeException("Sous-catégorie inconnue : $subCategoryName."));
            }

            // Le stock reste à 0 : sur commande
            foreach ($rows as $index => $row) {
                $product->addVariant((new ProductVariant())
                    ->setSize($row['taille'] ?: null)
                    ->setFabric($fabrics[$row['matiere']] ?? null)
                    ->setActualPrice($this->price($row, 'prix_ttc'))
                    ->setInitialPrice($this->price($row, $row['prix_barre_ttc'] !== '' ? 'prix_barre_ttc' : 'prix_ttc'))
                    ->setPosition($index + 1));
            }

            // Comme un envoi depuis l'admin : Vich copie chaque photo dans le dossier des produits, sous un nom unique.
            // La principale d'abord : la première de la galerie sert de vignette au catalogue.
            foreach (array_values(array_filter([$model['fichier_photo'], ...explode(' ', $model['autres_photos'])])) as $index => $file) {
                $media = (new Media())->setPosition($index + 1);
                $media->setImageFile(new ReplacingFile("$dir/photos/$file"));
                $product->addMedium($media);
            }

            // Prix « à partir de » et stock calculés tout de suite, pour que la validation les voie
            $product->refreshFromVariants();
            $violations = $this->validator->validate($product);
            foreach ($product->getVariants() as $variant) {
                $violations->addAll($this->validator->validate($variant));
            }
            if (count($violations) > 0) {
                throw new \RuntimeException("$name :\n$violations");
            }

            $new = [...$new, $product, ...$product->getMedia()];
            ++$count['Produits'];
            $count['Variantes'] += $product->getVariants()->count();
            $count['Photos'] += $product->getMedia()->count();

            $io->text(sprintf(
                '+ %s, %s > %s : à partir de %s €, variantes %d, photos %d',
                $name,
                $category->getName(),
                $rows[0]['sous_categories'],
                $product->getActualPrice(),
                $product->getVariants()->count(),
                $product->getMedia()->count(),
            ));
        }

        if (!$dryRun) {
            foreach ($new as $entity) {
                $this->em->persist($entity);   // les variantes suivent leur produit
            }
            $this->em->flush();
        }

        $io->newLine();
        $io->definitionList(...array_map(static fn (string $label, int $total) => [$label => $total], array_keys($count), $count));
        $dryRun ? $io->note('À blanc : rien n\'a été écrit.') : $io->success('Import terminé.');

        return Command::SUCCESS;
    }

    // Une matière neuve, avec ses coloris dans l'ordre du nuancier : chacun reçoit sa vignette
    // (envoyée comme depuis l'admin) et sa couleur moyenne, la couleur hex étant obligatoire.
    private function newFabric(string $name, string $supplier, array $finishes, string $dir): Fabric
    {
        $fabric = (new Fabric())->setName($name)->setSupplier($supplier);

        foreach ($finishes as $index => $finish) {
            $image = new ReplacingFile("$dir/{$finish['fichier_vignette']}");
            $color = (new FabricColor())
                ->setName($finish['finition'])
                ->setHex($this->averageHex($image->getPathname()))
                ->setPosition($index + 1);
            $color->setImageFile($image);
            $fabric->addFabricColor($color);
        }

        return $fabric;
    }

    // La couleur moyenne d'une image : réduite à un seul pixel, elle donne la moyenne de tous les autres.
    private function averageHex(string $path): string
    {
        $image = imagecreatefromstring(file_get_contents($path));
        $pixel = imagecreatetruecolor(1, 1);
        imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));

        return sprintf('#%06x', imagecolorat($pixel, 0, 0) & 0xFFFFFF);
    }

    // Un prix en euros, point décimal : « 1050 » ou « 1050.50 ».
    // Vérifié ici : le validateur compare un texte comme « 1 050,00 » à 0 sans signaler que ce n'est pas un nombre.
    private function price(array $row, string $column): string
    {
        return preg_match('/^\d+(\.\d{1,2})?$/', $row[$column])
            ? $row[$column]
            : throw new \RuntimeException(sprintf('%s invalide « %s » : %s, %s, %s.', $column, $row[$column], $row['nom_fournisseur'], $row['taille'], $row['gamme']));
    }
}
