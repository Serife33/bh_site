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

        // Filtre éventuel : lire ?sous-categorie=slug dans l'URL et retrouver la sous-catégorie
        $currentSubCategory = null;
        $slugSubCategory = $request->query->get('sous-categorie');
        if ($slugSubCategory) {
            $currentSubCategory = $subCategoryRepository->findOneBy(['slug' => $slugSubCategory]);
        }

        // Paginer ses produits actifs (Query non exécutée → le paginator ajoute le LIMIT)
        $pagination = $paginator->paginate(
            $productRepository->findActiveByCategoryQuery($category, $currentSubCategory),
            $request->query->getInt('page', 1),   // ?page=2 dans l'URL, défaut 1
            self::PRODUCTS_PER_PAGE
        );

        // 3. Envoyer à la vue
        return $this->render('front/category.html.twig', [
            'category' => $category,
            'subCategories' => $subCategories,
            'currentSubCategory' => $currentSubCategory,
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

}