<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use App\Repository\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Le fichier client du showroom
#[Route('/admin/client')]
final class CustomerController extends AbstractController
{
    private const CUSTOMERS_PER_PAGE = 20;

    #[Route('', name: 'app_customer_index', methods: ['GET'])]
    public function index(Request $request, CustomerRepository $customerRepository, PaginatorInterface $paginator): Response
    {
        $search = trim($request->query->getString('q'));

        return $this->render('customer/index.html.twig', [
            'pagination' => $paginator->paginate(
                $customerRepository->findForAdminQuery($search),
                $request->query->getInt('page', 1),
                self::CUSTOMERS_PER_PAGE,
            ),
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($customer);
            $em->flush();

            $this->addFlash('success', 'Client créé : vous pouvez lui faire un devis.');

            // La liste filtrée sur son nom : sa ligne porte le lien « Nouveau devis »
            return $this->redirectToRoute('app_customer_index', ['q' => $customer->getLastName()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Fiche client enregistrée.');

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $em, QuoteRepository $quoteRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $customer->getId(), $request->getPayload()->getString('_token'))) {
            if ($quoteRepository->customerHasQuotes($customer)) {
                $this->addFlash('error', sprintf('Impossible de supprimer « %s » : ce client a des devis. Supprimez-les d\'abord.', $customer));

                return $this->redirectToRoute('app_customer_edit', ['id' => $customer->getId()], Response::HTTP_SEE_OTHER);
            }

            $em->remove($customer);
            $em->flush();

            $this->addFlash('success', 'Client supprimé.');
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }
}
