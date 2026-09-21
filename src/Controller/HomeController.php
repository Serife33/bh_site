<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Service\HomeSectionsBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'front_home')]
    public function index(CategoryRepository $categoryRepository, HomeSectionsBuilder $homeSections): Response
    {
        // Les règles des trois sections sont dans src/Service/HomeSectionsBuilder.php
        $sections = $homeSections->build();

        return $this->render('home/index.html.twig', [
            'categories' => $categoryRepository->findBy([], ['id' => 'ASC']),
            'nouveautes' => $sections['nouveautes'],
            'promos'     => $sections['promos'],
            'enStock'    => $sections['enStock'],
        ]);
    }
}