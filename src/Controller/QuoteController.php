<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Family;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\InvoiceType;
use App\Enum\ProductModular;
use App\Enum\QuoteStatus;
use App\Form\QuoteType;
use App\Repository\AdminUserRepository;
use App\Repository\CustomerRepository;
use App\Repository\DocumentCounterRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use App\Repository\QuoteRepository;
use App\Service\QuoteCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/devis')]
final class QuoteController extends AbstractController
{
    private const QUOTES_PER_PAGE = 20;
    private const SEARCH_RESULTS = 20;   // produits ou clients trouvés

    public function __construct(private QuoteCalculator $calculator)
    {
    }

    #[Route('', name: 'app_quote_index', methods: ['GET'])]
    public function index(Request $request, QuoteRepository $quoteRepository, AdminUserRepository $adminUserRepository, PaginatorInterface $paginator): Response
    {
        $quoteRepository->expireOutdated();

        // Les filtres sont lus dans l'adresse : ils survivent à la pagination
        $search = trim($request->query->getString('q'));
        $status = QuoteStatus::tryFrom($request->query->getString('statut'));
        $sellerId = $request->query->getInt('vendeur');
        $seller = $sellerId ? $adminUserRepository->find($sellerId) : null;

        return $this->render('quote/index.html.twig', [
            'pagination' => $paginator->paginate(
                $quoteRepository->findForAdminQuery($search, $status, $seller),
                $request->query->getInt('page', 1),
                self::QUOTES_PER_PAGE,
            ),
            'statuses' => QuoteStatus::cases(),
            'sellers'  => $adminUserRepository->findSellers(),
            'filtres'  => ['q' => $search, 'statut' => $status, 'vendeur' => $seller?->getId()],
        ]);
    }

    // ?client=12 : le devis d'un client choisi dans le fichier client
    #[Route('/new', name: 'app_quote_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, CustomerRepository $customerRepository, DocumentCounterRepository $counters): Response
    {
        $customerId = $request->query->getInt('client');
        $quote = (new Quote())
            ->setSeller($this->getUser())   // le vendeur connecté, modifiable dans le formulaire
            ->setCustomer($customerId ? $customerRepository->findForQuote($customerId) : null)
            ->addLine(new QuoteLine());     // une première ligne prête à remplir
        $savedStatus = $quote->getStatus();   // la pastille dit le statut enregistré, pas celui en cours de saisie
        $form = $this->createForm(QuoteType::class, $quote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quote->setTotal($this->calculator->totals($quote)['ttc']);

            // Le numéro et le devis dans la même transaction : deux vendeurs ne reçoivent jamais le même numéro
            $em->wrapInTransaction(function () use ($em, $counters, $quote) {
                $quote->setNumber($counters->next(Quote::NUMBER_PREFIX, $quote->getCreatedAt()));
                $em->persist($quote);
            });

            return $this->saved($quote);
        }

        return $this->renderEdit($quote, $form, $savedStatus);
    }

    // La recherche de produit d'une ligne de devis (assets/devis.js) : à partir de 2 lettres, 20 produits au plus,
    // avec ce qu'il faut pour pré-remplir la ligne
    #[Route('/produits', name: 'app_quote_products', methods: ['GET'])]
    public function products(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $search = trim($request->query->getString('q'));
        $products = mb_strlen($search) < 2 ? [] : $productRepository->findForQuoteSearch($search, self::SEARCH_RESULTS);

        // Une gamme modulable trouvée (un de ses modules ou ensembles) : en premier, de quoi composer son canapé,
        // à partir de l'ensemble si la recherche n'a trouvé que lui dans la gamme
        $found = [];
        foreach ($products as $product) {
            if ($product->getFamily() && $product->getIsModular() !== ProductModular::No) {
                $found[$product->getFamily()->getId()][] = $product;
            }
        }
        $compositions = array_map(fn (array $family) => [
            'composition' => true,
            'name'        => $family['name'],
            'modules'     => (int) $family['modules'],
            'url'         => $this->generateUrl('app_quote_composition', ['id' => $family['id'], 'ensemble' => count($found[$family['id']]) === 1
                && $found[$family['id']][0]->getIsModular() === ProductModular::Yes ? $found[$family['id']][0]->getId() : null]),
        ], $productRepository->countActiveModulesOfFamilies(array_keys($found)));

        return $this->json([...$compositions, ...array_map($this->productData(...), $products)]);
    }

    // La fenêtre « Composer un canapé » d'une gamme (assets/devis.js) : ses modules, au départ aucun, ou un de chacun de
    // ceux de l'ensemble cherché, comme le configurateur du site ; les finitions de la gamme pour le tissu commun
    #[Route('/composition/{id}', name: 'app_quote_composition', methods: ['GET'])]
    public function composition(Request $request, Family $family, ProductRepository $productRepository): Response
    {
        $modules = $productRepository->findActiveModulesOfFamily($family);
        $ensemble = $request->query->getInt('ensemble') ? $productRepository->findForQuoteLine($request->query->getInt('ensemble')) : null;

        return $this->render('quote/_composition.html.twig', [
            'gamme'   => $family->getName(),
            'modules' => array_map(fn (Product $module) => [
                'produit' => $module,
                'donnees' => $this->productData($module),
                'nombre'  => (int) $ensemble?->getModules()->contains($module),
            ], $modules),
            'tissus'  => array_unique(array_merge([], ...array_map(static fn (Product $module) => array_column($module->getFinishes(), 'label'), $modules))),
        ]);
    }

    // La recherche du client d'un devis (assets/devis.js) : nom, prénom, téléphone ou e-mail, 20 clients au plus
    #[Route('/clients', name: 'app_quote_customers', methods: ['GET'])]
    public function customers(Request $request, CustomerRepository $customerRepository): JsonResponse
    {
        $search = trim($request->query->getString('q'));

        return $this->json(array_map($this->customerData(...), mb_strlen($search) < 2 ? [] : $customerRepository->findForQuoteSearch($search, self::SEARCH_RESULTS)));
    }

    // Un client tel que l'écran d'un devis le montre : sa fiche, et l'adresse de livraison
    private function customerData(Customer $customer): array
    {
        return [
            'id'      => $customer->getId(),
            'name'    => (string) $customer,
            'phone'   => $customer->getPhone(),
            'email'   => $customer->getEmail(),
            'address' => $customer->getAddress(),
            'url'     => $this->generateUrl('app_customer_edit', ['id' => $customer->getId()]),
        ];
    }

    // Un produit tel que l'écran d'un devis le connaît (assets/devis.js) : la recherche le renvoie,
    // une ligne enregistrée le porte déjà (quote/_article.html.twig)
    private function productData(Product $product): array
    {
        return [
            'id'         => $product->getId(),
            'name'       => $product->getName(),
            'price'      => $product->getActualPrice(),
            'customMade' => $product->isCustomMade(),
            'hidden'     => !$product->isActive(),   // masqué sur le site : proposé quand même, avec l'étiquette « masqué »
            'leadTime'   => $product->getLeadMaxWeeks() ? [$product->getLeadMinWeeks() ?? $product->getLeadMaxWeeks(), $product->getLeadMaxWeeks()] : null,
            'variants'   => $product->getVariants()->map(static fn (ProductVariant $variant) => [
                'id'       => $variant->getId(),
                'size'     => $variant->getSize(),
                'material' => $variant->getFabric() ? (string) $variant->getFabric() : null,
                'label'    => $variant->getLabel(),
                'price'    => $variant->getActualPrice(),
            ])->getValues(),
            'finishes'   => $product->getFinishes(),
        ];
    }

    #[Route('/{id}/edit', name: 'app_quote_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Quote $quote, EntityManagerInterface $em): Response
    {
        if ($quote->isLocked()) {
            $this->addFlash('warning', sprintf('Le devis %s est accepté : il ne se modifie plus.', $quote->getNumber()));

            return $this->redirectToRoute('app_quote_print', ['id' => $quote->getId()]);
        }

        $savedStatus = $quote->getStatus();   // la pastille dit le statut enregistré, pas celui en cours de saisie
        $form = $this->createForm(QuoteType::class, $quote);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $quote->setTotal($this->calculator->totals($quote)['ttc']);
            $em->flush();

            return $this->saved($quote);
        }

        return $this->renderEdit($quote, $form, $savedStatus);
    }

    // Le devis au format A4 : « Imprimer » puis « Enregistrer au format PDF » dans le navigateur
    #[Route('/{id}', name: 'app_quote_print', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function print(Quote $quote, InvoiceRepository $invoiceRepository): Response
    {
        return $this->render('quote/print.html.twig', [
            'quote'   => $quote,
            'totaux'  => $this->calculator->totals($quote),
            'invoice' => $invoiceRepository->findOneForQuote($quote),
            'balance' => $invoiceRepository->findOneForQuote($quote, InvoiceType::Balance),
        ]);
    }

    // Un devis se supprime complètement, quel que soit son statut (le papier signé fait foi), sauf s'il a une facture
    #[Route('/{id}', name: 'app_quote_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Quote $quote, EntityManagerInterface $em, InvoiceRepository $invoiceRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $quote->getId(), $request->getPayload()->getString('_token'))) {
            if ($invoiceRepository->findOneForQuote($quote) !== null) {
                $this->addFlash('error', sprintf('Impossible de supprimer le devis %s : il a une facture d\'acompte (un brouillon de facture se supprime d\'abord).', $quote->getNumber()));

                return $this->redirectToRoute('app_quote_index', [], Response::HTTP_SEE_OTHER);
            }

            $em->remove($quote);
            $em->flush();

            $this->addFlash('success', sprintf('Devis %s supprimé.', $quote->getNumber()));
        }

        return $this->redirectToRoute('app_quote_index', [], Response::HTTP_SEE_OTHER);
    }

    // Un devis accepté part sur sa page imprimable : il ne se modifie plus
    private function saved(Quote $quote): Response
    {
        $this->addFlash('success', sprintf('Devis %s enregistré.', $quote->getNumber()));

        return $this->redirectToRoute($quote->isLocked() ? 'app_quote_print' : 'app_quote_edit', ['id' => $quote->getId()], Response::HTTP_SEE_OTHER);
    }

    private function renderEdit(Quote $quote, FormInterface $form, QuoteStatus $savedStatus): Response
    {
        $products = array_filter($quote->getLines()->map(static fn (QuoteLine $line) => $line->getProduct())->toArray());

        return $this->render('quote/edit.html.twig', [
            'quote'     => $quote,
            'form'      => $form,
            'statut'    => $savedStatus,
            'totaux'    => $this->calculator->totals($quote),
            'client'    => $quote->getCustomer() ? $this->customerData($quote->getCustomer()) : null,
            'catalogue' => array_combine(array_map(static fn (Product $product) => $product->getId(), $products), array_map($this->productData(...), $products)),
        ]);
    }
}
