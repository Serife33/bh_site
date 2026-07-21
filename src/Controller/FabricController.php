<?php

namespace App\Controller;

use App\Entity\Fabric;
use App\Form\FabricType;
use App\Repository\FabricRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/fabric')]
final class FabricController extends AbstractController
{
    #[Route(name: 'app_fabric_index', methods: ['GET'])]
    public function index(FabricRepository $fabricRepository): Response
    {
        return $this->render('fabric/index.html.twig', [
            'fabrics' => $fabricRepository->findForIndex(),
        ]);
    }

    #[Route('/new', name: 'app_fabric_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $fabric = new Fabric();
        $form = $this->createForm(FabricType::class, $fabric);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($fabric);
            $entityManager->flush();

            return $this->redirectToRoute('app_fabric_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('fabric/new.html.twig', [
            'fabric' => $fabric,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_fabric_show', methods: ['GET'])]
    public function show(Fabric $fabric): Response
    {
        return $this->render('fabric/show.html.twig', [
            'fabric' => $fabric,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_fabric_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Fabric $fabric, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(FabricType::class, $fabric);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_fabric_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('fabric/edit.html.twig', [
            'fabric' => $fabric,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_fabric_delete', methods: ['POST'])]
    public function delete(Request $request, Fabric $fabric, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$fabric->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($fabric);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_fabric_index', [], Response::HTTP_SEE_OTHER);
    }
}
