<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'front_home')]
    public function index(CategoryRepository $categoryRepository, ProductRepository $productRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'categories' => $categoryRepository->findBy([], ['id' => 'ASC']),
            'nouveautes' => $productRepository->findLatestActive(8),
            'promos' => $productRepository->findOnSale(8),
            'enStock' => $productRepository->findInStock(8)
        ]);
    }
}
