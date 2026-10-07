<?php

namespace App\Controller;

use App\Entity\Media;
use App\Entity\Product;
use App\Form\MediaType;
use App\Form\PhotoBatchType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MediaRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/admin')]
final class MediaController extends AbstractController
{

    #[Route('/product/{id}/media/new', name: 'app_media_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function new(Request $request, Product $product, EntityManagerInterface $em, MediaRepository $mediaRepository): Response
    {
        // Requête tronquée par PHP : post_max_size dépassé. $_POST et $_FILES arrivent VIDES,
        // sans la moindre erreur — le formulaire se contenterait de dire « jeton invalide ».
        if ($request->isMethod('POST')
            && $request->request->count() === 0
            && (int) $request->server->get('CONTENT_LENGTH', 0) > 0) {
            $this->addFlash('error', 'Envoi trop lourd : PHP a refusé la requête entière. Réessaie avec moins de photos à la fois.');

            return $this->redirectToRoute('app_media_new', ['id' => $product->getId()]);
        }

        $form = $this->createForm(PhotoBatchType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $images = $form->get('images')->getData() ?? [];   // dans l'ordre de sélection
            $alts = $form->get('alts')->getData() ?? [];

            // Une seule interrogation de la base : les photos se rangent à la suite les unes des autres.
            $position = $mediaRepository->nextPosition($product);

            foreach (array_values($images) as $index => $image) {
                $media = new Media();
                $media->setProduct($product);
                $media->setImageFile($image);
                // Chaîne vide ramenée à null : côté public, « ?? » retombe sur le nom du produit,
                // et il n'attrape que null — une chaîne vide donnerait un vrai alt="".
                $media->setAlt(trim((string) ($alts[$index] ?? '')) ?: null);
                $media->setPosition($position++);

                $em->persist($media);
            }

            $em->flush();

            $total = count($images);
            $this->addFlash('success', $total > 1 ? $total . ' photos ajoutées.' : 'Photo ajoutée.');

            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }

        return $this->render('media/new.html.twig', [
            'form' => $form,
            'product' => $product,
        ]);
    }

    // Enregistre l'ordre envoyé par la grille de photos : renumérotation 1, 2, 3…
    #[Route('/media/ordre', name: 'app_media_order_save', methods: ['POST'])]
    public function orderSave(Request $request, MediaRepository $mediaRepository): JsonResponse
    {
        $payload = $request->getPayload();

        if (!$this->isCsrfTokenValid('ordre-photos', $payload->getString('_token'))) {
            return new JsonResponse(['ok' => false, 'message' => 'Jeton invalide.'], 403);
        }

        // Les identifiants dans leur nouvel ordre, nettoyés
        $ids = array_values(array_filter(array_map('intval', $payload->all('ids'))));

        $mediaRepository->updatePositions($ids);

        return new JsonResponse(['ok' => true, 'total' => count($ids)]);
    }


    // Enregistre le texte alternatif saisi dans la grille de photos, sans recharger la page.
    #[Route('/media/alt', name: 'app_media_alt_save', methods: ['POST'])]
    public function altSave(Request $request, MediaRepository $mediaRepository, EntityManagerInterface $em): JsonResponse
    {
        $payload = $request->getPayload();

        if (!$this->isCsrfTokenValid('alt-photos', $payload->getString('_token'))) {
            return new JsonResponse(['ok' => false, 'message' => 'Jeton invalide.'], 403);
        }

        // getString puis cast : getInt lève une exception sur une chaîne vide.
        $media = $mediaRepository->find((int) $payload->getString('id'));

        if ($media === null) {
            return new JsonResponse(['ok' => false, 'message' => 'Photo introuvable.'], 404);
        }

        // Un textarea accepte les retours à la ligne ; un attribut alt, non. On aplatit.
        $alt = trim(preg_replace('/\s+/', ' ', $payload->getString('alt')));

        // Vide ramené à null : côté public, « ?? » retombe sur le nom du produit et n'attrape que null.
        // Troncature à 180 : la longueur de la colonne, qu'une requête forgée pourrait dépasser.
        $media->setAlt($alt !== '' ? mb_substr($alt, 0, 180) : null);

        $em->flush();

        return new JsonResponse(['ok' => true, 'empty' => $media->getAlt() === null]);
    }

    #[Route('/media/{id}/edit', name:'app_media_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Media $media, EntityManagerInterface $em) : Response 
    {
        $form = $this->createForm(MediaType::class, $media, [
            'require_image' => false,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
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