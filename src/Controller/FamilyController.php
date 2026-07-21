<?php

namespace App\Controller;

use App\Entity\Family;
use App\Form\FamilyType;
use App\Repository\FamilyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/family')]
final class FamilyController extends AbstractController
{
    #[Route(name: 'app_family_index', methods: ['GET'])]
    public function index(FamilyRepository $familyRepository): Response
    {
        return $this->render('family/index.html.twig', [
            'families' => $familyRepository->findForIndex(),
        ]);
    }

    #[Route('/new', name: 'app_family_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $family = new Family();
        $form = $this->createForm(FamilyType::class, $family);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($family);
            $entityManager->flush();

            return $this->redirectToRoute('app_family_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('family/new.html.twig', [
            'family' => $family,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_family_show', methods: ['GET'])]
    public function show(Family $family): Response
    {
        return $this->render('family/show.html.twig', [
            'family' => $family,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_family_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Family $family, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FamilyType::class, $family);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_family_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('family/edit.html.twig', [
            'family' => $family,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_family_delete', methods: ['POST'])]
    public function delete(Request $request, Family $family, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$family->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($family);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_family_index', [], Response::HTTP_SEE_OTHER);
    }
}
