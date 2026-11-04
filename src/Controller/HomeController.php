<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\HomeSectionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'front_home')]
    public function index(CategoryRepository $categoryRepository, HomeSectionRepository $homeSectionRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'categories' => $categoryRepository->findForMenu(),
            // Les sections et leurs produits sont choisis dans l'admin (menu Accueil)
            'sections'   => $homeSectionRepository->findVisibleForHome(),
        ]);
    }
}
