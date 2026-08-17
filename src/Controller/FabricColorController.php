<?php

namespace App\Controller;

use App\Entity\FabricColor;
use App\Form\FabricColorType;
use App\Repository\FabricColorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/fabric/color')]
final class FabricColorController extends AbstractController
{
    #[Route(name: 'app_fabric_color_index', methods: ['GET'])]
    public function index(FabricColorRepository $fabricColorRepository): Response
    {
        return $this->render('fabric_color/index.html.twig', [
            'fabric_colors' => $fabricColorRepository->findForIndex(),
        ]);
    }

    #[Route('/new', name: 'app_fabric_color_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $fabricColor = new FabricColor();
        $form = $this->createForm(FabricColorType::class, $fabricColor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($fabricColor);
            $entityManager->flush();

            return $this->redirectToRoute('app_fabric_show', [
                'id' => $fabricColor->getFabric()->getId()],
                Response::HTTP_SEE_OTHER);
        }

        return $this->render('fabric_color/new.html.twig', [
            'fabric_color' => $fabricColor,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_fabric_color_show', methods: ['GET'])]
    public function show(FabricColor $fabricColor): Response
    {
        return $this->render('fabric_color/show.html.twig', [
            'fabric_color' => $fabricColor,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_fabric_color_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, FabricColor $fabricColor, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FabricColorType::class, $fabricColor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_fabric_show', [
                'id' => $fabricColor->getFabric()->getId()], 
                Response::HTTP_SEE_OTHER);
        }

        return $this->render('fabric_color/edit.html.twig', [
            'fabric_color' => $fabricColor,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_fabric_color_delete', methods: ['POST'])]
    public function delete(Request $request, FabricColor $fabricColor, EntityManagerInterface $entityManager): Response
    {
        $fabricId = $fabricColor->getFabric()->getId();

        if ($this->isCsrfTokenValid('delete'.$fabricColor->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($fabricColor);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_fabric_show', [
            'id' => $fabricId], 
            Response::HTTP_SEE_OTHER
        );
    }
}
