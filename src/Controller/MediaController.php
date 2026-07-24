<?php

namespace App\Controller;

use App\Entity\Media;
use App\Entity\Product;
use App\Form\MediaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class MediaController extends AbstractController
{

    private function ensureSingleMainPhoto(Media $media): void
    {
        // si la photo n'est pas principale : rien a faire 
        if (!$media->isMain()) {
            return;
        }

        // On parcourt toutes les photos du produit 
        foreach ($media->getProduct()->getMedia() as $otherMedia) {
            // on décoche toutes celles qui ne sont PAS celle qu'on vient de rendre principale
            if ($otherMedia !== $media) {
                $otherMedia->setIsMain(false);
            }
        }
    }


    #[Route('/product/{id}/media/new', name: 'app_media_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new (Request $request, Product $product, EntityManagerInterface $em): Response
    {
        $media = new Media();
        $media->setProduct($product);

        // Création du formulaire 
        $form = $this->createForm(MediaType::class, $media);
        $form->handleRequest($request); 
        
        if ($form->isSubmitted() && $form->isValid()) {
            $this->ensureSingleMainPhoto($media);
            $em->persist($media);
            $em->flush();

            $this->addFlash('success', 'Photo ajoutée.');

            return $this->redirectToRoute('app_product_show', [
                'id' => $product->getId() // remplit le {id} de la route show avec l'id du produit courant
            ]);
        }

        return $this->render('media/new.html.twig', [
            'form' => $form,
            'product' => $product
        ]);
    }

    #[Route('/media/{id}/edit', name:'app_media_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Media $media, EntityManagerInterface $em) : Response 
    {
        $form = $this->createForm(MediaType::class, $media, [
            'require_image' => false,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $this->ensureSingleMainPhoto($media);
            $em->flush();

            $this->addFlash('success', 'Photo modifiée.');

            return $this->redirectToRoute('app_product_show', [
                'id' => $media->getProduct()->getId(),
            ]);
        };
        
        return $this->render('media/edit.html.twig', [
            'form' => $form,
            'product' => $media->getProduct(),
        ]);
    }

    #[Route('/media/{id}/delete', name: 'app_media_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Media $media, EntityManagerInterface $em): Response
    {
        $productId = $media->getProduct()->getId();

        if($this->isCsrfTokenValid('delete'.$media->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($media);
            $em->flush();

            $this->addFlash('success', 'Photo supprimée.');
        }

        return $this->redirectToRoute('app_product_show', [
            'id' => $productId,
        ]);
    }
}