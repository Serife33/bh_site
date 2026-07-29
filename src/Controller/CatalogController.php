<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
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
    public function category(
        string $slug,
        Request $request,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        PaginatorInterface $paginator
    ): Response {
        // Retrouver la catégorie par son slug (ou 404 si elle n'existe pas)
        $category = $categoryRepository->findOneBy(['slug' => $slug]);
        if (!$category) {
            throw $this->createNotFoundException('Cette catégorie n\'existe pas.');
        }

        // Paginer ses produits actifs (Query non exécutée → le paginator ajoute le LIMIT)
        $pagination = $paginator->paginate(
            $productRepository->findActiveByCategoryQuery($category),
            $request->query->getInt('page', 1),   // ?page=2 dans l'URL, défaut 1
            self::PRODUCTS_PER_PAGE
        );

        // 3. Envoyer à la vue
        return $this->render('front/category.html.twig', [
            'category' => $category,
            'pagination' => $pagination,
        ]);
    }
}