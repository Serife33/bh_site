<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/product')]
final class ProductController extends AbstractController
{
    // nombre de produits affichés par page 
    private const PRODUCTS_PER_PAGE = 20;

    #[Route('', name: 'app_product_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        PaginatorInterface $paginator  // le service de pagination 
    ): Response {
        $pagination = $paginator->paginate(
            $productRepository->findAllOrderedQuery(), // Query non executée
            $request->query->getInt('page', 1), // numéro de page lu dans l'URL (?page=2), défaut 1
            self::PRODUCTS_PER_PAGE,  // nombre de produits max par page 
        );
        return $this->render('product/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    // Créer un produit — GET affiche le formulaire, POST le traite
    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();

            $this->addFlash('success', 'Produit créé avec succès.');

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/new.html.twig', [
            'form' => $form,
        ]);
    }

    // Détail d'un produit — GET /admin/product/12 
    #[Route('/{id}', name: 'app_product_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Product $product) : Response
    {
        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);

    }

    // Modifier un produit — GET affiche le form pré-rempli, POST enregistre
    #[Route('/{id}/edit', name: 'app_product_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Produit modifié avec succès.');

            return $this->redirectToRoute('app_product_index');
        }


        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form
        ]);
    }

    // Supprimer un produit
    #[Route('/{id}', name: 'app_product_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, product $product, EntityManagerInterface $em): Response
    {
        // On vérifie le jeton CSRF avant toute suppression
        if($this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))){
        $em->remove($product);
        $em->flush();

        $this->addFlash('success', 'Produit supprimé.');

        }
        return $this->redirectToRoute('app_product_index'); 
    }
      
}
