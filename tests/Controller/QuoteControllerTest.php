<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Product;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\ProductModular;
use App\Enum\ProductSide;
use App\Enum\QuoteStatus;
use App\Repository\InvoiceRepository;
use App\Service\QuoteCalculator;
use App\Twig\Extension\AppExtension;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// Le parcours d'un devis dans l'administration, de la saisie à la page imprimable
final class QuoteControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private AdminUser $seller;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();   // une seule connexion pour tout le test : la transaction couvre toutes les requêtes
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->em->beginTransaction();    // annulée dans tearDown : le test ne laisse rien en base

        $this->seller = (new AdminUser())->setEmail('vendeur-test@example.com')->setRoles(['ROLE_ADMIN'])->setPassword('');
        $this->customer = (new Customer())->setLastName('Exemple')->setFirstName('Client')
            ->setPhone('06 00 00 00 01')->setAddress("1 rue de l'Exemple, 33000 Bordeaux");
        $this->em->persist($this->seller);
        $this->em->persist($this->customer);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        parent::tearDown();
    }

    #[DataProvider('adminPages')]
    public function testPagesAreForAdminsOnly(string $url): void
    {
        $this->client->request('GET', $url);

        self::assertResponseRedirects('/login');
    }

    public static function adminPages(): iterable
    {
        yield 'liste des devis' => ['/admin/devis'];
        yield 'nouveau devis' => ['/admin/devis/new'];
        yield 'fichier client' => ['/admin/client'];
    }

    // Le devis BHD-2026-11-001 de la maquette, saisi dans le formulaire, puis sa page imprimable
    public function testCreateTheMaquetteQuoteThenPrintIt(): void
    {
        $product = $this->product("Canapé d'angle LONDON");
        $this->client->loginUser($this->seller);

        $url = '/admin/devis/new?client=' . $this->customer->getId();
        $values = $this->client->request('GET', $url)->filter('#form-devis')->form()->getPhpValues();
        $values['quote']['lines'] = [
            $this->line("Canapé d'angle LONDON", '1590.00', '10', 'percent', ['product' => $product->getId(), 'finish' => 'Chenille Monica, Terra Cota - 1012', 'customMade' => '1']),
            $this->line('Lit coffre PARIS', '1155.00', '75', 'amount', ['details' => 'Taille 160x200']),
            $this->line('Tapis sur mesure 200x300', '480.00', '0', 'percent', ['customMade' => '1']),
        ];
        $values['quote'] = ['discountValue' => '90', 'discountType' => 'amount', 'deliveryFee' => '120.00', 'assemblyFee' => '60.00'] + $values['quote'];
        $this->client->request('POST', $url, $values);

        $quote = $this->em->getRepository(Quote::class)->findOneBy(['customer' => $this->customer]);
        self::assertResponseRedirects('/admin/devis/' . $quote->getId() . '/edit');
        self::assertMatchesRegularExpression('/^BHD-' . date('Y-m') . '-\d{3}$/', $quote->getNumber());
        self::assertSame('3081.00', $quote->getTotal());
        self::assertSame($this->seller->getId(), $quote->getSeller()->getId());   // le vendeur connecté, choisi d'office
        self::assertSame($product->getId(), $quote->getLines()->first()->getProduct()->getId());

        // La page imprimable affiche exactement les montants du service
        $crawler = $this->client->request('GET', '/admin/devis/' . $quote->getId());
        self::assertResponseIsSuccessful();
        $totals = (new QuoteCalculator())->totals($quote);
        foreach (['items', 'delivery', 'assembly', 'ht', 'tva', 'ttc', 'deposit', 'balance'] as $key) {
            self::assertSame((new AppExtension())->prix($totals[$key], 2), $crawler->filter("[data-total=$key]")->text(), $key);
        }
        self::assertSame("-90,00\u{A0}€", $crawler->filter('[data-total=discount]')->text());
        self::assertSame("3\u{A0}081,00\u{A0}€", $crawler->filter('[data-total=ttc]')->text());
        self::assertSame("924,30\u{A0}€", $crawler->filter('[data-total=deposit]')->text());
        self::assertSame(["1\u{A0}431,00\u{A0}€", "1\u{A0}080,00\u{A0}€", "480,00\u{A0}€"], $crawler->filter('.lignes tbody td:last-child')->each(fn ($td) => $td->text()));
        self::assertSelectorTextContains('.lignes tbody tr:first-child', 'soit -159,00');
        self::assertSelectorCount(2, '.choix');                            // « Fabriqué selon vos choix » : canapé et tapis
        self::assertSelectorTextContains('.doc', 'vendeur-test@example.com');   // « Votre conseiller »
        self::assertSelectorTextContains('.pied .ac', '[À COMPLÉTER');     // l'identité légale reste à fournir
    }

    public function testInvalidAmountsAreRefused(): void
    {
        $this->client->loginUser($this->seller);

        $values = $this->client->request('GET', '/admin/devis/new')->filter('#form-devis')->form()->getPhpValues();
        $values['quote']['customer'] = $this->customer->getId();
        $values['quote']['lines'] = [$this->line('Ligne test', '-5.00', '150', 'percent')];
        $this->client->request('POST', '/admin/devis/new', $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#form-devis', 'Le prix ne peut pas être négatif.');
        self::assertSelectorTextContains('#form-devis', 'Une remise ne peut pas dépasser 100 %.');
    }

    public function testAcceptedQuoteIsLocked(): void
    {
        $quote = $this->quote(QuoteStatus::Accepted);
        $this->client->loginUser($this->seller);

        $this->client->request('GET', '/admin/devis/' . $quote->getId() . '/edit');

        self::assertResponseRedirects('/admin/devis/' . $quote->getId());
        self::assertNotNull($quote->getAcceptedAt());
    }

    public function testListExpiresOutdatedQuotesAndFilters(): void
    {
        $outdated = $this->quote(QuoteStatus::Sent)->setValidUntil(new \DateTimeImmutable('-1 day'));
        $draft = $this->quote(QuoteStatus::Draft);
        $this->em->flush();
        $this->client->loginUser($this->seller);

        $crawler = $this->client->request('GET', '/admin/devis?statut=expired&vendeur=' . $this->seller->getId());

        self::assertResponseIsSuccessful();
        $this->em->refresh($outdated);
        self::assertSame(QuoteStatus::Expired, $outdated->getStatus());
        self::assertStringContainsString($outdated->getNumber(), $crawler->filter('table')->text());
        self::assertStringNotContainsString($draft->getNumber(), $crawler->filter('table')->text());
    }

    public function testDeleteQuoteThenItsCustomer(): void
    {
        $quote = $this->quote(QuoteStatus::Sent);
        $quoteId = $quote->getId();
        $this->client->loginUser($this->seller);

        // Un client qui a des devis ne se supprime pas
        $this->client->submit($this->client->request('GET', '/admin/client/' . $this->customer->getId() . '/edit')->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/admin/client/' . $this->customer->getId() . '/edit');

        $this->client->submit($this->client->request('GET', '/admin/devis/' . $quoteId)->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/admin/devis');
        self::assertNull($this->em->find(Quote::class, $quoteId));

        $this->client->submit($this->client->request('GET', '/admin/client/' . $this->customer->getId() . '/edit')->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/admin/client');
    }

    public function testDepositInvoiceIsNumberedWhenIssuedThenKept(): void
    {
        $quote = $this->quote(QuoteStatus::Accepted);   // une ligne à 100 € : acompte de 30 €, dont 25 € HT
        $invoices = self::getContainer()->get(InvoiceRepository::class);
        $this->client->loginUser($this->seller);
        $createInvoice = fn () => $this->client->submit($this->client->request('GET', '/admin/devis/' . $quote->getId())->selectButton("Créer la facture d'acompte")->form());

        // En brouillon, sans numéro : elle se supprime
        $createInvoice();
        $draft = $invoices->findOneForQuote($quote);
        self::assertResponseRedirects('/admin/facture/' . $draft->getId());
        self::assertNull($draft->getNumber());
        $this->client->submit($this->client->request('GET', '/admin/facture/' . $draft->getId())->selectButton('Supprimer')->form());
        self::assertNull($invoices->findOneForQuote($quote));

        // Émise : elle reçoit son numéro, et le formulaire de suppression du brouillon, gardé, n'y fait plus rien
        $createInvoice();
        $invoiceId = $invoices->findOneForQuote($quote)->getId();
        $crawler = $this->client->request('GET', '/admin/facture/' . $invoiceId);
        $staleDelete = $crawler->selectButton('Supprimer')->form();
        $this->client->submit($crawler->selectButton('Émettre la facture')->form());
        $this->client->submit($staleDelete);

        $invoice = $invoices->find($invoiceId);
        self::assertMatchesRegularExpression('/^BHF-' . date('Y-m') . '-\d{3}$/', $invoice->getNumber());
        self::assertNotNull($invoice->getIssuedAt());
        self::assertSame('Client Exemple', $invoice->getCustomerName());

        $this->client->request('GET', '/admin/facture/' . $invoiceId);
        self::assertSelectorTextContains('.encadre-acompte', "25,00\u{A0}€");
        self::assertSelectorTextContains('.doc', $invoice->getNumber());
        self::assertSelectorNotExists('.barre-ecran form');   // ni émission ni suppression

        // Le devis qui a une facture ne se supprime plus
        $this->client->submit($this->client->request('GET', '/admin/devis/' . $quote->getId())->selectButton('Supprimer')->form());
        self::assertNotNull($this->em->find(Quote::class, $quote->getId()));
    }

    private function line(string $label, string $unitPrice, string $discount, string $type, array $more = []): array
    {
        return $more + ['label' => $label, 'quantity' => '1', 'unitPrice' => $unitPrice, 'discountValue' => $discount, 'discountType' => $type];
    }

    private function quote(QuoteStatus $status): Quote
    {
        static $number = 0;
        $quote = (new Quote())->setNumber(sprintf('BHD-2000-01-%03d', ++$number))->setStatus($status)
            ->setCustomer($this->customer)->setSeller($this->seller)
            ->addLine((new QuoteLine())->setLabel('Ligne test')->setUnitPrice('100.00'));
        $this->em->persist($quote);
        $this->em->flush();

        return $quote;
    }

    private function product(string $name): Product
    {
        $category = (new Category())->setName('Catégorie test devis');
        $product = (new Product())->setName($name)->setCategory($category)->setInitialPrice('1590.00')->setActualPrice('1590.00')
            ->setStock(0)->setIsCustomMade(true)->setIsModular(ProductModular::No)->setSideLr(ProductSide::Right)
            ->setPosition(1)->setIsActive(true);
        $this->em->persist($category);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }
}
