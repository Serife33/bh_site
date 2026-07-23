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
    #[Route('/product/{id}/media/new', name: 'app_media_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new (Request $request, Product $product, EntityManagerInterface $em): Response
    {
        $media = new Media();
        $media->setProduct($product);

        // Création du formulaire 
        $form = $this->createForm(MediaType::class, $media);
        $form->handleRequest($request); 
        
        if ($form->isSubmitted() && $form->isValid()) {
            
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