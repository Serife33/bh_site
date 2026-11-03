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
 * Tout ou rien : tout est contrôlé avant la première copie de photo, puis la base est écrite en une seule transaction.
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
        #[Argument('Le fournisseur écrit comme dans l\'admin (ex. Masami) : il retrouve ses matières et signe les nouvelles')] string $fournisseur,
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

        $rowsByModel = [];
        foreach ($this->csv->read($tableur, self::COLUMNS) as $row) {
            $rowsByModel[$row['id_page'] . ';' . $row['nom_fournisseur']][] = $row;
        }

        $count = ['Produits' => 0, 'Variantes' => 0, 'Matières' => 0, 'Coloris' => 0, 'Photos' => 0, 'Produits déjà en base, ignorés' => 0];
        $new = [];   // tout ce qui sera enregistré, d'un coup, à la fin

        // Les matières, désignées par leur libellé dans l'admin : « Céramique (Premium) ».
        // Une matière déjà en base garde ses coloris tels quels : ils ont pu être renommés dans l'admin.
        $fabrics = [];
        foreach ($this->fabricRepository->findBySupplier($fournisseur) as $fabric) {
            $fabrics[(string) $fabric] = $fabric;
        }

        // Les produits, un par modèle
        $positions = [];
        $slugs = [];
        foreach ($rowsByModel as $key => $rows) {
            $model = $models[$key] ?? throw new \RuntimeException("Modèle absent de modeles.csv : $key.");
            $name = $model['nom_propose_fr'];
            $slug = Product::slugFrom($name);

            // Refusé dès l'à-blanc : en base, l'adresse unique ferait échouer l'import après la copie des photos
            if (isset($slugs[$slug])) {
                throw new \RuntimeException("Deux modèles donnent l'adresse « $slug » : renommez-en un dans modeles.csv.");
            }
            $slugs[$slug] = true;

            if ($this->productRepository->slugExists($slug)) {
                ++$count['Produits déjà en base, ignorés'];
                $io->text("= $name : déjà en base, ignoré");

                continue;
            }

            $category = $this->categoryRepository->findOneByName($rows[0]['categorie'])
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
                $product->addSubCategory($this->subCategoryRepository->findOneByName($subCategoryName)
                    ?? throw new \RuntimeException("Sous-catégorie inconnue : $subCategoryName."));
            }

            // Une variante par ligne, stock à 0 : sur commande. Une matière absente de la base est créée à sa première variante.
            $variantKeys = [];
            foreach ($rows as $index => $row) {
                $variantKey = "{$row['taille']}, {$row['matiere']}";
                if (isset($variantKeys[$variantKey])) {
                    throw new \RuntimeException("$name : deux lignes pour la même variante ($variantKey).");
                }
                $variantKeys[$variantKey] = true;

                $fabric = $row['matiere'] === ''
                    ? null
                    : ($fabrics[$row['matiere']] ??= $this->newFabric($row['matiere'], $fournisseur, $finishes[$row['gamme']] ?? [], $dir));

                $product->addVariant((new ProductVariant())
                    ->setSize($row['taille'] ?: null)
                    ->setFabric($fabric)
                    ->setActualPrice($this->price($row, 'prix_ttc'))
                    ->setInitialPrice($this->price($row, $row['prix_barre_ttc'] !== '' ? 'prix_barre_ttc' : 'prix_ttc'))
                    ->setPosition($index + 1));
            }

            // Comme un envoi depuis l'admin : Vich copie chaque photo dans le dossier des produits, sous un nom unique.
            // La principale d'abord : la première de la galerie sert de vignette au catalogue.
            foreach (array_values(array_filter([$model['fichier_photo'], ...explode(' ', $model['autres_photos'])])) as $index => $file) {
                $media = (new Media())->setPosition($index + 1);
                $media->setImageFile($this->image("$dir/photos", $file));
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

        // Les matières neuves : celles qui n'ont pas encore d'identifiant
        foreach ($fabrics as $label => $fabric) {
            if ($fabric->getId() === null) {
                $new = [...$new, $fabric, ...$fabric->getFabricColors()];
                ++$count['Matières'];
                $count['Coloris'] += $fabric->getFabricColors()->count();
                $io->text(sprintf('+ matière %s, %d coloris', $label, $fabric->getFabricColors()->count()));
            }
        }

        if (!$dryRun) {
            foreach ($new as $entity) {
                $this->em->persist($entity);   // Vich copie la photo ici ; les variantes suivent leur produit
            }
            $this->em->flush();
        }

        $io->newLine();
        $io->definitionList(...array_chunk($count, 1, true));
        $dryRun ? $io->note('À blanc : rien n\'a été écrit.') : $io->success('Import terminé.');

        return Command::SUCCESS;
    }

    // Une matière neuve, avec ses coloris dans l'ordre du nuancier : chacun reçoit sa vignette
    // (envoyée comme depuis l'admin) et sa couleur moyenne, la couleur hex étant obligatoire.
    private function newFabric(string $name, string $supplier, array $finishes, string $dir): Fabric
    {
        $fabric = (new Fabric())->setName($name)->setSupplier($supplier);

        foreach ($finishes as $index => $finish) {
            $image = $this->image($dir, $finish['fichier_vignette']);
            $color = (new FabricColor())
                ->setName($finish['finition'])
                ->setHex($this->averageHex($image->getPathname()))
                ->setPosition($index + 1);
            $color->setImageFile($image);
            $fabric->addFabricColor($color);
        }

        return $fabric;
    }

    // Une image nommée dans un CSV, prise uniquement dans son dossier : un nom piégé (« ../../.env.local »)
    // serait sinon copié dans public/uploads, à la vue de tous. Seules les photos JPEG, PNG et WebP passent.
    private function image(string $folder, string $file): ReplacingFile
    {
        $path = realpath("$folder/$file");   // le chemin réel, liens symboliques suivis ; false si le fichier est absent

        if (str_contains($file, '..') || str_starts_with($file, '/') || $path === false || !str_starts_with($path, realpath($folder) . '/')) {
            throw new \RuntimeException("Image refusée, absente ou hors de son dossier : $file.");
        }

        $image = new ReplacingFile($path);

        return in_array($image->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)
            ? $image
            : throw new \RuntimeException("Image refusée, ce n'est pas une photo JPEG, PNG ou WebP : $file.");
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
