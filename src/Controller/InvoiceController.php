<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Quote;
use App\Enum\InvoiceType;
use App\Repository\DocumentCounterRepository;
use App\Repository\InvoiceRepository;
use App\Service\QuoteCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

// Les factures d'un devis accepté, acompte puis solde : créées en brouillon, puis émises avec leur numéro
#[Route('/admin/facture')]
final class InvoiceController extends AbstractController
{
    // Depuis la page imprimable du devis. Une seule facture de chaque type : on y retourne si elle existe.
    // Le devis relu sous verrou : un double clic ne crée qu'une facture.
    #[Route('/devis/{id}/{type}', name: 'app_invoice_new', methods: ['POST'], requirements: ['id' => '\d+', 'type' => new EnumRequirement(InvoiceType::class)], defaults: ['type' => 'deposit'])]
    public function new(Request $request, Quote $quote, InvoiceType $type, InvoiceRepository $invoiceRepository, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('invoice' . $quote->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_quote_print', ['id' => $quote->getId()], Response::HTTP_SEE_OTHER);
        }

        $invoice = $em->wrapInTransaction(function () use ($quote, $type, $invoiceRepository, $em) {
            $invoiceRepository->lock($quote);
            $invoice = $invoiceRepository->findOneForQuote($quote, $type);
            $ready = match ($type) {
                InvoiceType::Deposit => $quote->isLocked(),
                InvoiceType::Balance => $invoiceRepository->findOneForQuote($quote)?->isIssued() === true,
            };

            if ($invoice === null && $ready) {
                $invoice = new Invoice($quote, $type);
                $em->persist($invoice);
            }

            return $invoice;
        });

        if ($invoice === null) {
            $this->addFlash('error', "La facture d'acompte demande un devis accepté, la facture de solde une facture d'acompte émise.");

            return $this->redirectToRoute('app_quote_print', ['id' => $quote->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_invoice_print', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_invoice_print', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function print(Invoice $invoice, QuoteCalculator $calculator, InvoiceRepository $invoiceRepository): Response
    {
        return $this->render('invoice/print.html.twig', [
            'invoice' => $invoice,
            'acompte' => $invoiceRepository->findOneForQuote($invoice->getQuote()),   // déduit sur la facture de solde
            'totaux'  => $calculator->totals($invoice->getQuote()),
        ]);
    }

    // Le numéro et la facture dans la même transaction : si l'enregistrement échoue, le compteur revient en arrière
    // et la numérotation reste sans trou. La facture relue sous verrou : un double clic n'émet qu'une fois.
    #[Route('/{id}/emettre', name: 'app_invoice_issue', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function issue(Request $request, Invoice $invoice, EntityManagerInterface $em, InvoiceRepository $invoiceRepository, DocumentCounterRepository $counters): Response
    {
        if ($this->isCsrfTokenValid('issue' . $invoice->getId(), $request->getPayload()->getString('_token'))) {
            $em->wrapInTransaction(function () use ($invoice, $invoiceRepository, $counters) {
                $invoiceRepository->lock($invoice);
                if (!$invoice->isIssued()) {
                    $now = new \DateTimeImmutable();
                    $invoice->issue($counters->next(Invoice::NUMBER_PREFIX, $now), $now);
                }
            });

            $this->addFlash('success', sprintf('Facture %s émise.', $invoice->getNumber()));
        }

        return $this->redirectToRoute('app_invoice_print', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }

    // Seul un brouillon se supprime : une facture émise reste, une erreur se corrigera par un avoir.
    // Relue sous verrou : une émission lancée en même temps passe avant ou après, jamais à moitié.
    #[Route('/{id}', name: 'app_invoice_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Invoice $invoice, EntityManagerInterface $em, InvoiceRepository $invoiceRepository): Response
    {
        if ($this->isCsrfTokenValid('delete' . $invoice->getId(), $request->getPayload()->getString('_token'))) {
            $em->wrapInTransaction(function () use ($em, $invoice, $invoiceRepository) {
                $invoiceRepository->lock($invoice);
                if (!$invoice->isIssued()) {
                    $em->remove($invoice);
                }
            });
        }

        return $this->redirectToRoute('app_quote_print', ['id' => $invoice->getQuote()->getId()], Response::HTTP_SEE_OTHER);
    }
}
