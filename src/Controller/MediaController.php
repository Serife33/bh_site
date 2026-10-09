<?php

namespace App\Controller;

use App\Entity\Media;
use App\Entity\Product;
use App\Form\MediaType;
use App\Form\PhotoBatchType;
use App\Form\VideoUploadType;
use App\Service\YouTubeMetadataBuilder;
use App\Service\YouTubeUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\MediaRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Storage\StorageInterface;

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


    #[Route('/product/{id}/video/new', name: 'app_media_video_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function videoNew(
        Request $request,
        Product $product,
        EntityManagerInterface $em,
        MediaRepository $mediaRepository,
        YouTubeUploader $uploader,
        YouTubeMetadataBuilder $metadata,
        StorageInterface $storage,
    ): Response {
        // Même piège que pour les photos : post_max_size dépassé, PHP livre une requête vide.
        if ($request->isMethod('POST')
            && $request->request->count() === 0
            && (int) $request->server->get('CONTENT_LENGTH', 0) > 0) {
            $this->addFlash('error', 'Envoi trop lourd : PHP a refusé la requête entière. Réduis le poids de la vidéo.');

            return $this->redirectToRoute('app_media_video_new', ['id' => $product->getId()]);
        }

        // Les quatre clés sont listées même vides : avec un tableau en guise de données,
        // une clé absente ferait échouer la lecture du champ correspondant.
        $form = $this->createForm(VideoUploadType::class, [
            'video' => null,
            'cover' => null,
            'title' => $metadata->buildTitle($product),
            'description' => $metadata->buildDescription($product),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $video = $form->get('video')->getData();
            $cover = $form->get('cover')->getData();
            $title = trim((string) $form->get('title')->getData());
            $description = trim((string) $form->get('description')->getData());

            // L'envoi peut durer plusieurs minutes, PHP coupe à 30 s par défaut.
            set_time_limit(0);

            // La couverture est décidée AVANT d'envoyer quoi que ce soit : sans image
            // fournie, on reprend la photo principale du produit. Vérifié maintenant,
            // pour ne jamais laisser une vidéo orpheline sur la chaîne.
            if ($cover === null) {
                $cover = $this->coverFromMainPhoto($product, $storage);

                if ($cover === null) {
                    $this->addFlash('error', "Ce produit n'a aucune photo : ajoute d'abord une photo, ou fournis une image de couverture.");

                    return $this->redirectToRoute('app_media_video_new', ['id' => $product->getId()]);
                }

                $this->addFlash('warning', "Aucune couverture fournie : la photo principale du produit a été reprise.");
            }

            try {
                $videoId = $uploader->upload($video->getPathname(), $title, $description);
            } catch (\Throwable $e) {
                // On sort avant tout persist : pas de ligne orpheline pointant vers une vidéo absente.
                $this->addFlash('error', 'YouTube a refusé l\'envoi : ' . $e->getMessage());

                return $this->redirectToRoute('app_media_video_new', ['id' => $product->getId()]);
            }

            // Aucune miniature n'est envoyée à YouTube : les vidéos sont verticales et
            // courtes, donc classées en Shorts, et l'API refuse d'y poser une miniature.
            // La couverture ne sert qu'au site. YouTubeUploader::setThumbnail() reste
            // en place pour le jour où des vidéos horizontales arriveront.

            $media = new Media();
            $media->setProduct($product);
            $media->setType(Media::TYPE_YOUTUBE);
            $media->setVideoId($videoId);
            $media->setAlt($title);
            $media->setPosition($mediaRepository->nextPosition($product));
            $media->setImageFile($cover);

            $em->persist($media);
            $em->flush();

            $this->addFlash('success', 'Vidéo envoyée sur YouTube et ajoutée à la galerie.');

            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()]);
        }

        return $this->render('media/video_new.html.twig', [
            'form' => $form,
            'product' => $product,
        ]);
    }


    /**
     * Reprend la photo principale du produit comme couverture de la vidéo.
     *
     * On en fait une COPIE : Vich déplace le fichier qu'on lui confie, et donner
     * l'original ferait disparaître la photo de la galerie.
     */
    private function coverFromMainPhoto(Product $product, StorageInterface $storage): ?UploadedFile
    {
        $photo = $product->getMainMedia();

        if ($photo === null) {
            return null;
        }

        $source = $storage->resolvePath($photo, 'imageFile');

        if ($source === null || !is_file($source)) {
            return null;
        }

        $copy = sprintf(
            '%s/cover-%d-%s.%s',
            sys_get_temp_dir(),
            $photo->getId(),
            uniqid(),
            pathinfo($source, PATHINFO_EXTENSION),
        );

        if (!copy($source, $copy)) {
            return null;
        }

        // Le dernier argument à true : le fichier ne vient pas d'un envoi HTTP,
        // sans ça Symfony refuserait de le déplacer.
        return new UploadedFile($copy, basename($source), null, null, true);
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