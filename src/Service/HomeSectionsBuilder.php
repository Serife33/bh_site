<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;

/**
 * Choisit les produits des trois sections de l'accueil.
 *
 * Règles :
 *  - 8 produits au plus par section ; en dessous de 4, la section est masquée ;
 *  - un seul produit par famille ;
 *  - 2 produits par catégorie, complétés au-delà s'il en manque pour arriver à 8 ;
 *  - un produit n'apparaît qu'une fois sur la page : Promotions choisit en premier,
 *    puis Disponible immédiatement, puis Nouveautés (qui a toujours de quoi se remplir) ;
 *  - affichage : le classement du glisser-déposer, en alternant les catégories
 *    dans l'ordre de « Nos univers ».
 */
final class HomeSectionsBuilder
{
    private const MAX_PER_SECTION     = 8;
    private const MIN_PER_SECTION     = 1;
    private const MAX_PER_CATEGORY    = 2;
    private const LATEST_ARRIVAL_DAYS = 7;   // Nouveautés : le « dernier arrivage » d'une famille

    // Identifiants des produits déjà placés plus haut dans la page
    private array $alreadyShown = [];

    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * @return array{promos: Product[], enStock: Product[], nouveautes: Product[]}
     */
    public function build(): array
    {
        // Une seule requête : tous les produits visibles hors modules
        $products = $this->productRepository->findActiveForHome();
        $this->alreadyShown = [];

        // 1. Promotions — représentant d'une famille : le moins cher de ses produits en promo
        $promos = $this->buildSection(
            array_filter($products, fn (Product $p) => (float) $p->getActualPrice() < (float) $p->getInitialPrice()),
            $this->cheapest(...),
            $this->alternateCategories(...),
        );

        // 2. Disponible immédiatement — représentant : le mieux classé de ses produits en stock
        $inStock = $this->buildSection(
            array_filter($products, fn (Product $p) => $p->getStock() > 0),
            $this->bestRanked(...),
            $this->alternateCategories(...),
        );

        // 3. Nouveautés — représentant : le mieux classé du dernier arrivage de la famille,
        //    et les familles les plus récentes sont choisies en premier
        $newArrivals = $this->buildSection(
            $products,
            $this->bestRankedOfLatestArrival(...),
            $this->sortByNewest(...),
        );

        return [
            'promos'     => $promos,
            'enStock'    => $inStock,
            'nouveautes' => $newArrivals,
        ];
    }

    /**
     * Construit une section.
     *
     * @param Product[] $candidates         les produits qui ont leur place dans la section
     * @param callable  $pickRepresentative choisit LE produit qui représente une famille
     * @param callable  $prioritize         range les représentants : les premiers sont choisis d'abord
     */
    private function buildSection(array $candidates, callable $pickRepresentative, callable $prioritize): array
    {
        // Écarter les produits déjà placés plus haut dans la page
        $candidates = array_filter($candidates, fn (Product $p) => !isset($this->alreadyShown[$p->getId()]));

        // Un seul produit par famille
        $representatives = array_map($pickRepresentative, $this->groupByFamily($candidates));

        // Les 8 élus : 2 par catégorie d'abord, puis complément
        $selected = $this->applyLimits($prioritize($representatives));

        // Moins de 4 : section masquée, et ses produits restent libres pour les sections suivantes
        if (count($selected) < self::MIN_PER_SECTION) {
            return [];
        }

        foreach ($selected as $product) {
            $this->alreadyShown[$product->getId()] = true;
        }

        // Ordre d'affichage : le classement, en alternant les catégories
        return $this->alternateCategories($selected);
    }

    /**
     * Regroupe les produits par famille. Un produit sans famille forme une famille à lui seul.
     *
     * @return Product[][]
     */
    private function groupByFamily(array $products): array
    {
        $families = [];

        foreach ($products as $product) {
            $key = $product->getFamily()?->getId() ?? 'alone-' . $product->getId();
            $families[$key][] = $product;
        }

        return array_values($families);
    }

    /**
     * Garde 8 produits au plus : 2 par catégorie au premier passage,
     * puis complète sans limite de catégorie s'il en manque.
     */
    private function applyLimits(array $ordered): array
    {
        $selected   = [];
        $byCategory = [];

        // 1er passage : 2 par catégorie au maximum
        foreach ($ordered as $product) {
            if (count($selected) === self::MAX_PER_SECTION) {
                break;
            }

            $categoryId = $product->getCategory()->getId();

            if (($byCategory[$categoryId] ?? 0) < self::MAX_PER_CATEGORY) {
                $selected[$product->getId()] = $product;
                $byCategory[$categoryId]     = ($byCategory[$categoryId] ?? 0) + 1;
            }
        }

        // 2e passage : s'il manque des produits, on complète, dans le même ordre
        foreach ($ordered as $product) {
            if (count($selected) === self::MAX_PER_SECTION) {
                break;
            }

            $selected[$product->getId()] ??= $product;
        }

        return array_values($selected);
    }

    /**
     * Ordre d'affichage : le n° 1 de chaque catégorie, puis le n° 2 de chaque catégorie…
     * Les catégories passent dans l'ordre de « Nos univers » (par identifiant, voir HomeController).
     */
    private function alternateCategories(array $products): array
    {
        $byCategory = [];

        foreach ($products as $product) {
            $byCategory[$product->getCategory()->getId()][] = $product;
        }

        ksort($byCategory);
        $byCategory = array_map($this->sortByRank(...), $byCategory);

        $result = [];

        for ($rank = 0; count($result) < count($products); $rank++) {
            foreach ($byCategory as $group) {
                if (isset($group[$rank])) {
                    $result[] = $group[$rank];
                }
            }
        }

        return $result;
    }

    /** Les plus récents d'abord (Nouveautés). */
    private function sortByNewest(array $products): array
    {
        usort($products, fn (Product $a, Product $b) =>
            [$b->getCreatedAt(), $b->getId()] <=> [$a->getCreatedAt(), $a->getId()]);

        return $products;
    }

    /** Tri selon le glisser-déposer : position, puis identifiant pour départager. */
    private function sortByRank(array $products): array
    {
        usort($products, fn (Product $a, Product $b) =>
            [$a->getPosition(), $a->getId()] <=> [$b->getPosition(), $b->getId()]);

        return $products;
    }

    /** Représentant : le mieux classé. */
    private function bestRanked(array $family): Product
    {
        return $this->sortByRank($family)[0];
    }

    /** Représentant (Promotions) : le moins cher ; à prix égal, le mieux classé. */
    private function cheapest(array $family): Product
    {
        usort($family, fn (Product $a, Product $b) =>
            [(float) $a->getActualPrice(), $a->getPosition(), $a->getId()]
            <=> [(float) $b->getActualPrice(), $b->getPosition(), $b->getId()]);

        return $family[0];
    }

    /**
     * Représentant (Nouveautés) : le mieux classé parmi les produits de la famille
     * créés dans les 7 jours avant le plus récent — son dernier arrivage.
     */
    private function bestRankedOfLatestArrival(array $family): Product
    {
        $newest    = max(array_map(fn (Product $p) => $p->getCreatedAt(), $family));
        $threshold = $newest->modify('-' . self::LATEST_ARRIVAL_DAYS . ' days');

        return $this->bestRanked(
            array_filter($family, fn (Product $p) => $p->getCreatedAt() >= $threshold)
        );
    }
}