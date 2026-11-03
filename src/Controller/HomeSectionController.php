<?php

namespace App\Controller;

use App\Entity\HomeSection;
use App\Form\HomeSectionType;
use App\Repository\HomeSectionProductRepository;
use App\Repository\HomeSectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Les sections de produits de l'accueil : la liste se range au glisser-déposer, comme la page d'une section ses produits.
#[Route('/admin/home-section')]
final class HomeSectionController extends AbstractController
{
    use OrderSaveTrait;

    #[Route(name: 'app_home_section_index', methods: ['GET'])]
    public function index(HomeSectionRepository $homeSectionRepository): Response
    {
        return $this->render('home_section/index.html.twig', [
            'home_sections' => $homeSectionRepository->findForIndex(),
        ]);
    }

    // Avant les routes /{id} : « ordre » n'est pas un identifiant
    #[Route('/ordre', name: 'app_home_section_order_save', methods: ['POST'])]
    public function orderSave(Request $request, HomeSectionRepository $homeSectionRepository): JsonResponse
    {
        return $this->saveOrder($request, 'ordre', $homeSectionRepository->updatePositions(...));
    }

    #[Route('/produits/ordre', name: 'app_home_section_products_order_save', methods: ['POST'])]
    public function productsOrderSave(Request $request, HomeSectionProductRepository $homeSectionProductRepository): JsonResponse
    {
        return $this->saveOrder($request, 'ordre', $homeSectionProductRepository->updatePositions(...));
    }

    #[Route('/new', name: 'app_home_section_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, HomeSectionRepository $homeSectionRepository): Response
    {
        $homeSection = new HomeSection();
        $form = $this->createForm(HomeSectionType::class, $homeSection);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // La position n'est pas saisie : la section se range à la fin de l'accueil
            $homeSection->setPosition($homeSectionRepository->nextPosition());
            $entityManager->persist($homeSection);
            $entityManager->flush();

            // Sa page, où se range l'ordre de ses produits
            return $this->redirectToRoute('app_home_section_show', ['id' => $homeSection->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('home_section/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_home_section_show', methods: ['GET'])]
    public function show(HomeSection $homeSection): Response
    {
        return $this->render('home_section/show.html.twig', [
            'home_section' => $homeSection,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_home_section_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, HomeSection $homeSection, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(HomeSectionType::class, $homeSection);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_home_section_show', ['id' => $homeSection->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('home_section/edit.html.twig', [
            'home_section' => $homeSection,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_home_section_delete', methods: ['POST'])]
    public function delete(Request $request, HomeSection $homeSection, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$homeSection->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($homeSection);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_home_section_index', [], Response::HTTP_SEE_OTHER);
    }
}
