<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Fabric;
use App\Entity\Family;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\SubCategory;
use App\Enum\ProductModular;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }


    // Liste du back-office : tous les produits, modules et masqués compris,
    // filtrés par la barre de recherche. Chaque critère est optionnel : null = pas de filtre.
    // Projection → des tableaux, pas des objets : seulement les colonnes affichées.
    public function findForAdminQuery(
        ?string $search = null,
        ?Category $category = null,
        ?SubCategory $subCategory = null,
        ?bool $inStock = null,
        ?bool $visible = null,
    ): Query {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id', 'p.name', 'p.actualPrice', 'p.stock', 'p.position', 'p.isActive', 'c.name AS categoryName')
            ->leftJoin('p.category', 'c')
            ->orderBy('c.name', 'ASC')      // les produits d'une même catégorie restent groupés
            ->addOrderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC');    // départage : sans clé finale unique, la pagination oublie des lignes

        if ($search !== null && $search !== '') {
            $this->andWhereNameContains($qb, $search);
        }

        if ($category !== null) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $category);
        }

        if ($subCategory !== null) {
            $qb->join('p.subCategories', 'sc')
               ->andWhere('sc = :subCategory')
               ->setParameter('subCategory', $subCategory);
        }

        if ($inStock !== null) {
            $qb->andWhere($inStock ? 'p.stock > 0' : 'p.stock = 0');
        }

        if ($visible !== null) {
            $qb->andWhere('p.isActive = :visible')
               ->setParameter('visible', $visible);
        }

        return $qb->getQuery();   // non exécutée : le paginator ajoutera le LIMIT
    }

    // Page « Ordre d'affichage » : tous les produits d'une catégorie, dans l'ordre du site.
    // Modules et produits masqués compris : chacun garde une vraie place.
    // Projection → des tableaux, pas des objets : seulement ce que la liste affiche.
    public function findForOrdering(Category $category): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id', 'p.name', 'p.isActive', 'p.isModular')
            ->andWhere('p.category = :category')
            ->setParameter('category', $category)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC') // départage : deux positions égales sortent toujours dans le même ordre
            ->getQuery()
            ->getArrayResult();
    }


    // Enregistre un nouvel ordre : le 1er identifiant reçoit la position 1, le 2e la position 2…
    // UPDATE direct : seule la colonne position change, updatedAt ne bouge pas
    // (le lastmod du sitemap et le tri des promos restent justes).
    // Tout ou rien : si une requête échoue, aucune position n'est modifiée.
    public function updatePositions(array $ids): void
    {
        $requete = $this->createQueryBuilder('p')
            ->update()
            ->set('p.position', ':position')
            ->where('p.id = :id')
            ->getQuery();

        $this->getEntityManager()->wrapInTransaction(function () use ($requete, $ids) {
            foreach (array_values($ids) as $rang => $id) {
                $requete->setParameter('position', $rang + 1)
                        ->setParameter('id', $id)
                        ->execute();
            }
        });
    }


    // La première place libre dans une catégorie : un produit neuf — ou une copie —
    // se range à la fin, il ne vient jamais s'asseoir sur la place d'un autre.
    public function nextPosition(?Category $category): int
    {
        if ($category === null) {
            return 1;
        }

        $max = $this->createQueryBuilder('p')
            ->select('MAX(p.position)')
            ->andWhere('p.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;   // catégorie vide : MAX vaut null, la première place est 1
    }

    // La première adresse libre : $base, sinon $base-2, $base-3…
    // Le produit $exceptId est ignoré : régénérer sa propre adresse ne le met pas en conflit avec lui-même.
    public function uniqueSlug(string $base, ?int $exceptId): string
    {
        $taken = $this->createQueryBuilder('p')
            ->select('p.slug')
            ->andWhere('p.slug LIKE :prefix')   // candidates : $base et ses variantes, la boucle tranche
            ->andWhere('p.id != :exceptId')
            ->setParameter('prefix', $base . '%')
            ->setParameter('exceptId', $exceptId ?? 0)   // 0 : aucun produit, cas de la création
            ->getQuery()
            ->getSingleColumnResult();

        $slug = $base;
        for ($i = 2; in_array($slug, $taken, true); ++$i) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    // Un produit a-t-il déjà cette adresse ? L'import ne recrée pas un modèle déjà en base.
    public function slugExists(string $slug): bool
    {
        return $this->count(['slug' => $slug]) > 0;
    }

    // Accueil : tous les produits visibles hors modules, avec leurs photos (pour les cartes).
    // Le service HomeSectionsBuilder applique ensuite les règles des trois sections.
    public function findActiveForHome(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.media', 'm')
            ->addSelect('m')    // les photos arrivent avec les produits : pas une requête par carte
            ->andWhere('p.isActive = true')
            ->andWhere('p.isModular != :module') // Les modules ne s'affichent que sur la fiche de leur ensemble.
            ->setParameter('module', ProductModular::Module)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByCategoryQuery(Category $category, ?SubCategory $subCategory = null): Query
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.media', 'm')
            ->addSelect('m')
            ->andWhere('p.isActive = true')
            ->andWhere('p.category = :category')
            ->andWhere('p.isModular != :module') // Les modules ne s'affichent que sur la fiche de leur ensemble, jamais dans les listes du catalogue.
            ->setParameter('category', $category)
            ->setParameter('module', ProductModular::Module)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC');

        // Filtre optionnel : seulement si une sous-catégorie est choisie
        if ($subCategory !== null) {
            $qb->join('p.subCategories', 'sc')
            ->andWhere('sc = :subCategory')
            ->setParameter('subCategory', $subCategory);
        }

        return $qb->getQuery();
    }

    // La fiche publique : le produit visible et ce qu'elle affiche autour, une collection par requête
    // (jointes ensemble, variantes, finitions, tissus et coloris multiplieraient les lignes).
    // Les photos arrivent au premier accès, en une requête.
    public function findActiveBySlug(string $slug): ?Product
    {
        $product = $this->createQueryBuilder('p')
            ->join('p.category', 'c')
            ->leftJoin('p.family', 'f')
            ->leftJoin('p.variants', 'v')
            ->leftJoin('v.fabric', 'vf')
            ->addSelect('c', 'f', 'v', 'vf')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.isActive = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();

        if ($product === null) {
            return null;
        }

        $this->loadFinishes([$product]);

        return $product;
    }

    // Les tissus et leurs coloris, les finitions et celles des matières des variantes de ces produits :
    // Doctrine remplit les collections des produits déjà chargés, une requête par collection
    // au lieu d'une par produit et par tissu au premier accès.
    private function loadFinishes(array $products): void
    {
        if ($products === []) {
            return;
        }

        $this->createQueryBuilder('p')
            ->leftJoin('p.fabrics', 'fa')
            ->leftJoin('fa.fabricColors', 'fc')
            ->addSelect('fa', 'fc')
            ->andWhere('p IN (:products)')
            ->setParameter('products', $products)
            ->orderBy('fa.id', 'ASC')   // l'ordre de la table de liaison, celui du chargement au premier accès
            ->getQuery()
            ->getResult();

        $this->createQueryBuilder('p')
            ->leftJoin('p.colors', 'co')
            ->addSelect('co')
            ->andWhere('p IN (:products)')
            ->setParameter('products', $products)
            ->getQuery()
            ->getResult();

        // Les matières des variantes et leurs finitions
        $matieres = array_filter(array_merge(...array_map(
            static fn (Product $product) => $product->getVariants()->map(static fn ($variant) => $variant->getFabric())->toArray(),
            $products,
        )));
        if ($matieres) {
            $this->getEntityManager()->createQueryBuilder()
                ->select('m', 'fi')
                ->from(Fabric::class, 'm')
                ->leftJoin('m.fabricColors', 'fi')
                ->andWhere('m IN (:matieres)')
                ->setParameter('matieres', $matieres)
                ->getQuery()
                ->getResult();
        }
    }

    public function findSimilar(Product $product, int $limit = 4): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.subCategories', 'sc')
            ->andWhere('p.isActive = true')
            ->andWhere('p != :current')          // exclure le produit lui-même
            ->andWhere('p.isModular != :module')
            ->setParameter('module', ProductModular::Module)
            ->setParameter('current', $product)
            ->setMaxResults($limit)
            ->distinct();

        // Similarité : même sous-catégorie OU même famille
        $ou = $qb->expr()->orX();

        $sousCatIds = $product->getSubCategories()->map(fn($sc) => $sc->getId())->toArray();
        if ($sousCatIds) {
            $ou->add('sc.id IN (:sousCatIds)');
            $qb->setParameter('sousCatIds', $sousCatIds);
        }
        if ($product->getFamily()) {
            $ou->add('p.family = :family');
            $qb->setParameter('family', $product->getFamily());
        }

        if ($ou->count() === 0) {
            return [];   // ni sous-cat ni famille → aucun similaire
        }

        $similar = $qb->andWhere($ou)->getQuery()->getResult();

        // Les photos des cartes en une requête. Pas dans la première : son LIMIT compterait les photos, pas les produits.
        $this->createQueryBuilder('p')
            ->leftJoin('p.media', 'm')
            ->addSelect('m')
            ->andWhere('p IN (:similar)')
            ->setParameter('similar', $similar)
            ->getQuery()
            ->getResult();

        return $similar;
    }

    public function searchActiveQuery(string $q): Query
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.media', 'm')
            ->addSelect('m')
            ->andWhere('p.isActive = true')
            ->andWhere('p.name LIKE :q OR p.description LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery();   // non exécutée → le paginator ajoute le LIMIT
    }


    public function findActiveForSitemap(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.slug', 'p.updatedAt')   // projection : juste ce qu'il faut
            ->andWhere('p.isActive = true')
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }


    // Les modules proposables dans le formulaire d'un ensemble modulable.
    // Trois conditions : c'est un module, ce n'est pas le produit lui-même, c'est la même famille.
    // Retourne un QueryBuilder et non une Query : EntityType a besoin de pouvoir le compléter.
    public function createModulesQueryBuilder(Product $product): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isModular = :module')
            ->setParameter('module', ProductModular::Module)
            ->orderBy('p.name', 'ASC');

        // Exclure le produit lui-même, seulement s'il existe déjà en base
        // (une copie en cours de création n'a pas encore d'identifiant)
        if ($product->getId() !== null) {
            $qb->andWhere('p != :courant')
               ->setParameter('courant', $product);
        }

        if ($product->getFamily() !== null) {
            $qb->andWhere('p.family = :famille')
               ->setParameter('famille', $product->getFamily());
        }

        return $qb;
    }

    // La recherche de produit d'une ligne de devis : les premiers par ordre alphabétique, masqués et modules
    // compris (le showroom les vend aussi), avec leurs variantes (chargées ensemble, en une requête) et leurs finitions
    public function findForQuoteSearch(string $search, int $limit): array
    {
        $products = $this->andWhereNameContains($this->createQueryBuilder('p'), $search)
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $this->loadFinishes($products);

        return $products;
    }

    // Les modules actifs d'une gamme, dans leur ordre d'affichage, avec leurs photos, tissus et finitions : un nombre fixe
    // de requêtes, quel que soit le nombre de modules. Pour composer un canapé dans un devis.
    public function findActiveModulesOfFamily(Family $family): array
    {
        $modules = $this->createQueryBuilder('p')
            ->leftJoin('p.media', 'm')
            ->addSelect('m')   // photos dans l'ordre de leur collection (position)
            ->andWhere('p.family = :family')
            ->andWhere('p.isModular = :module')
            ->andWhere('p.isActive = true')
            ->setParameter('family', $family)
            ->setParameter('module', ProductModular::Module)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        $this->loadFinishes($modules);

        return $modules;
    }

    // Les gammes de ces identifiants qui ont des modules actifs, avec leur nombre : de quoi proposer de composer un canapé
    /** @return list<array{id: int, name: string, modules: int}> */
    public function countActiveModulesOfFamilies(array $familyIds): array
    {
        if ($familyIds === []) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->select('f.id', 'f.name', 'COUNT(p.id) AS modules')
            ->join('p.family', 'f')
            ->andWhere('f.id IN (:families)')
            ->andWhere('p.isModular = :module')
            ->andWhere('p.isActive = true')
            ->setParameter('families', $familyIds)
            ->setParameter('module', ProductModular::Module)
            ->groupBy('f.id', 'f.name')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    // Le produit et la variante d'une ligne de devis, reçus par leur identifiant
    public function findForQuoteLine(int $id): ?Product
    {
        return $this->find($id);
    }

    public function findVariantForQuoteLine(int $id): ?ProductVariant
    {
        return $this->getEntityManager()->find(ProductVariant::class, $id);
    }

    // La recherche par nom des listes de l'admin, partagée par la liste des produits et les lignes de devis.
    // Les % et _ tapés sont cherchés tels quels, pas comme des jokers.
    private function andWhereNameContains(QueryBuilder $qb, string $search): QueryBuilder
    {
        return $qb->andWhere('p.name LIKE :search')
            ->setParameter('search', '%' . addcslashes($search, '%_\\') . '%');
    }

    // Les produits dont une variante est dans cette matière : tant qu'il y en a, elle ne se supprime pas.
    public function findNamesUsingFabric(Fabric $fabric): array
    {
        return $this->createQueryBuilder('p')
            ->select('DISTINCT p.name')
            ->join('p.variants', 'v')
            ->andWhere('v.fabric = :fabric')
            ->setParameter('fabric', $fabric)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
