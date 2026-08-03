<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route('/mentions-legales', name: 'front_mentions_legales', methods: ['GET'])]
    public function mentionsLegales(): Response
    {
        return $this->render('front/legal/mentions_legales.html.twig');
    }

    #[Route('/politique-confidentialite', name: 'front_confidentialite', methods: ['GET'])]
    public function confidentialite(): Response
    {
        return $this->render('front/legal/confidentialite.html.twig');
    }

    #[Route('/contact', name: 'front_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('front/contact.html.twig');
    }
}
