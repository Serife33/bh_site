<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Quote;
use App\Repository\DocumentCounterRepository;
use App\Repository\InvoiceRepository;
use App\Service\QuoteCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// La facture d'acompte d'un devis accepté : créée en brouillon, puis émise avec son numéro
#[Route('/admin/facture')]
final class InvoiceController extends AbstractController
{
    // Depuis la page imprimable du devis. Un devis n'a qu'une facture d'acompte : on y retourne si elle existe.
    #[Route('/devis/{id}', name: 'app_invoice_new', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function new(Request $request, Quote $quote, InvoiceRepository $invoiceRepository, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('invoice' . $quote->getId(), $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('app_quote_print', ['id' => $quote->getId()], Response::HTTP_SEE_OTHER);
        }

        $invoice = $invoiceRepository->findOneForQuote($quote);

        if ($invoice === null) {
            if (!$quote->isLocked()) {
                $this->addFlash('error', "Seul un devis accepté donne une facture d'acompte.");

                return $this->redirectToRoute('app_quote_edit', ['id' => $quote->getId()], Response::HTTP_SEE_OTHER);
            }

            $invoice = new Invoice($quote);
            $em->persist($invoice);
            $em->flush();
        }

        return $this->redirectToRoute('app_invoice_print', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_invoice_print', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function print(Invoice $invoice, QuoteCalculator $calculator): Response
    {
        return $this->render('invoice/print.html.twig', [
            'invoice' => $invoice,
            'totaux'  => $calculator->totals($invoice->getQuote()),
        ]);
    }

    // Le numéro et la facture dans la même transaction : si l'enregistrement échoue, le compteur revient en arrière
    // et la numérotation reste sans trou
    #[Route('/{id}/emettre', name: 'app_invoice_issue', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function issue(Request $request, Invoice $invoice, EntityManagerInterface $em, DocumentCounterRepository $counters): Response
    {
        if ($this->isCsrfTokenValid('issue' . $invoice->getId(), $request->getPayload()->getString('_token')) && !$invoice->isIssued()) {
            $now = new \DateTimeImmutable();
            $em->wrapInTransaction(fn () => $invoice->issue($counters->next(Invoice::NUMBER_PREFIX, $now), $now));

            $this->addFlash('success', sprintf('Facture %s émise.', $invoice->getNumber()));
        }

        return $this->redirectToRoute('app_invoice_print', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
    }

    // Seul un brouillon se supprime : une facture émise reste, une erreur se corrigera par un avoir
    #[Route('/{id}', name: 'app_invoice_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Invoice $invoice, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $invoice->getId(), $request->getPayload()->getString('_token')) && !$invoice->isIssued()) {
            $em->remove($invoice);
            $em->flush();
        }

        return $this->redirectToRoute('app_quote_print', ['id' => $invoice->getQuote()->getId()], Response::HTTP_SEE_OTHER);
    }
}
