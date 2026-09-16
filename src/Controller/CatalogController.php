<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\SubCategoryRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CatalogController extends AbstractController
{
    // nombre de produits affichés par page sur une catégorie
    private const PRODUCTS_PER_PAGE = 12;

    #[Route('/categorie/{slug}', name: 'front_category', methods: ['GET'])]
    public function category(string $slug, Request $request, CategoryRepository $categoryRepository, ProductRepository $productRepository, SubCategoryRepository $subCategoryRepository, PaginatorInterface $paginator): Response 
    {
        // Retrouver la catégorie par son slug (ou 404 si elle n'existe pas)
        $category = $categoryRepository->findOneBy(['slug' => $slug]);
        if (!$category) {
            throw $this->createNotFoundException('Cette catégorie n\'existe pas.');
        }

        // Les sous-catégories utiles de cette catégorie (pour les puces de filtre)
        $subCategories = $subCategoryRepository->findUsedInCategory($category);

        // L'ancienne adresse ?sous-categorie=… a sa propre page maintenant : redirection définitive
        $slugSubCategory = $request->query->get('sous-categorie');
        if ($slugSubCategory) {
            $ancienne = $subCategoryRepository->findOneBy(['slug' => $slugSubCategory]);
            if ($ancienne) {
                return $this->redirectToRoute('front_subcategory', [
                    'slug' => $category->getSlug(),
                    'sousCategorie' => $ancienne->getSlug(),
                ], 301);
            }
        }
        $currentSubCategory = null;

        // ?page= dans l'URL, 1 par défaut
        $page = $request->query->getInt('page', 1);
        if ($page < 1) {
            throw $this->createNotFoundException('Cette page n\'existe pas.');
        }

        // Paginer ses produits actifs (Query non exécutée → le paginator ajoute le LIMIT)
        $pagination = $paginator->paginate(
            $productRepository->findActiveByCategoryQuery($category, $currentSubCategory),
            $page,
            self::PRODUCTS_PER_PAGE
        );

        // Au-delà de la dernière page il n'y a rien : 404 plutôt qu'une grille vide
        $lastPage = max(1, (int) ceil($pagination->getTotalItemCount() / self::PRODUCTS_PER_PAGE));
        if ($page > $lastPage) {
            throw $this->createNotFoundException('Cette page n\'existe pas.');
        }

        // 3. Envoyer à la vue
        return $this->render('front/category.html.twig', [
            'category' => $category,
            'subCategories' => $subCategories,
            'currentSubCategory' => $currentSubCategory,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/categorie/{slug}/{sousCategorie}', name: 'front_subcategory', methods: ['GET'])]
    public function subCategory(
        string $slug,
        string $sousCategorie,
        Request $request,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        SubCategoryRepository $subCategoryRepository,
        PaginatorInterface $paginator
    ): Response {
        $category = $categoryRepository->findOneBy(['slug' => $slug]);
        if (!$category) {
            throw $this->createNotFoundException('Cette catégorie n\'existe pas.');
        }

        $subCategory = $subCategoryRepository->findOneBy(['slug' => $sousCategorie]);
        if (!$subCategory) {
            throw $this->createNotFoundException('Cette sous-catégorie n\'existe pas.');
        }

        $page = $request->query->getInt('page', 1);
        if ($page < 1) {
            throw $this->createNotFoundException('Cette page n\'existe pas.');
        }

        $pagination = $paginator->paginate(
            $productRepository->findActiveByCategoryQuery($category, $subCategory),
            $page,
            self::PRODUCTS_PER_PAGE
        );

        // Une sous-catégorie sans produit dans cette catégorie n'a pas de page
        if ($pagination->getTotalItemCount() === 0) {
            throw $this->createNotFoundException('Cette page n\'existe pas.');
        }

        $lastPage = max(1, (int) ceil($pagination->getTotalItemCount() / self::PRODUCTS_PER_PAGE));
        if ($page > $lastPage) {
            throw $this->createNotFoundException('Cette page n\'existe pas.');
        }

        return $this->render('front/subcategory.html.twig', [
            'category' => $category,
            'subCategory' => $subCategory,
            'subCategories' => $subCategoryRepository->findUsedInCategory($category),
            'pagination' => $pagination,
        ]);
    }

    #[Route('/produit/{slug}', name: 'front_product', methods: ['GET'])]
    public function product(string $slug, ProductRepository $productRepository): Response
    {
        // Retrouver le produit ACTIF par son slug (ou 404)
        $product = $productRepository->findOneBy(['slug' => $slug, 'isActive' => true]);
        if (!$product) {
            throw $this->createNotFoundException('Ce produit n\'existe pas.');
        }

        $similar = $productRepository->findSimilar($product);

        return $this->render('front/product.html.twig', [
            'product' => $product,
            'similar' => $similar,
        ]);
    }

    #[Route('/recherche', name: 'front_search', methods: ['GET'])]
    public function search(Request $request, ProductRepository $productRepository, PaginatorInterface $paginator): Response
    {
        $q = trim($request->query->get('q', ''));

        $pagination = null;
        if ($q !== '') {
            $pagination = $paginator->paginate(
                $productRepository->searchActiveQuery($q),
                $request->query->getInt('page', 1),
                self::PRODUCTS_PER_PAGE
            );
        }

        return $this->render('front/search.html.twig', [
            'q' => $q,
            'pagination' => $pagination,
        ]);
    }

}