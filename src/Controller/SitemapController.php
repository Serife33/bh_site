<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'front_sitemap', methods: ['GET'])]
    public function sitemap(CategoryRepository $categoryRepository, ProductRepository $productRepository): Response
    {
        $abs = UrlGeneratorInterface::ABSOLUTE_URL;   // URL complète (http://…)
        $urls = [];

        // Pages statiques
        foreach (['front_home', 'front_contact', 'front_mentions_legales', 'front_confidentialite'] as $route) {
            $urls[] = ['loc' => $this->generateUrl($route, [], $abs)];
        }

        // Catégories
        foreach ($categoryRepository->findForIndex() as $cat) {
            $urls[] = ['loc' => $this->generateUrl('front_category', ['slug' => $cat['slug']], $abs)];
        }

        // Produits actifs
        foreach ($productRepository->findActiveForSitemap() as $p) {
            $urls[] = [
                'loc' => $this->generateUrl('front_product', ['slug' => $p['slug']], $abs),
                'lastmod' => $p['updatedAt']?->format('Y-m-d'),
            ];
        }

        $response = new Response('', 200, ['Content-Type' => 'application/xml']);
        return $this->render('front/sitemap.xml.twig', ['urls' => $urls], $response);
    }
}