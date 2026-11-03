<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Fabric;
use App\Entity\FabricColor;
use App\Entity\Invoice;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\DiscountType;
use App\Enum\ProductModular;
use App\Enum\ProductSide;
use App\Enum\QuoteStatus;
use App\Repository\InvoiceRepository;
use App\Service\QuoteCalculator;
use App\Twig\Extension\AppExtension;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
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
        yield 'recherche de produit' => ['/admin/devis/produits?q=madrid'];
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
        $values['quote'] = ['discountValue' => '90', 'discountType' => 'amount', 'delivery' => '1', 'deliveryFee' => '120.00', 'assembly' => '1', 'assemblyFee' => '60.00',
            'leadTime' => "6 à 10 semaines après réception de l'acompte"] + $values['quote'];
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
        self::assertSelectorTextContains('.pied', 'SIRET 901 674 507 00016');   // l'identité légale de la société
        self::assertSelectorNotExists('.pied .ac');                         // plus rien à compléter dans le pied
    }

    // « Je tape Madrid et je retrouve tous les Madrid » : 20 au plus, avec leurs variantes et leurs tissus
    public function testProductSearchForQuoteLines(): void
    {
        $fabric = (new Fabric())->setName('Chenille')->setReference('Essai')->addFabricColor((new FabricColor())->setName('Terra')->setHex('#B16042'));
        $this->em->persist($fabric);
        $first = $this->product('Canapé ESSAIMADRID 01')->addFabric($fabric)
            ->addVariant((new ProductVariant())->setSize('160x200')->setInitialPrice('1155.00')->setActualPrice('1155.00'));
        for ($i = 2; $i <= 25; ++$i) {
            $this->product(sprintf('Canapé ESSAIMADRID %02d', $i));
        }
        $this->client->loginUser($this->seller);

        $products = $this->searchProducts('essaimadrid');
        self::assertCount(20, $products);
        self::assertSame(['id' => $first->getId(), 'name' => 'Canapé ESSAIMADRID 01', 'price' => '1155.00', 'customMade' => true, 'leadTime' => null,
            'variants' => [['id' => $first->getVariants()->first()->getId(), 'size' => '160x200', 'material' => null, 'label' => 'Taille 160x200', 'price' => '1155.00']],
            'finishes' => [['material' => null, 'label' => 'Chenille Essai, Terra']]], $products[0]);
        self::assertSame('Canapé ESSAIMADRID 20', $products[19]['name']);   // par ordre alphabétique
        self::assertCount(20, $this->searchProducts('madrid'));
        foreach ($this->searchProducts('madrid') as $product) {
            self::assertStringContainsStringIgnoringCase('madrid', $product['name']);
        }

        // Trop court, ou des caractères spéciaux cherchés tels quels : rien, et pas d'erreur
        self::assertSame([], $this->searchProducts('m'));
        self::assertSame([], $this->searchProducts("%' OR 1=1 -- "));
        self::assertSame([], $this->searchProducts('%%'));
    }

    public function testForeignVariantIsRefused(): void
    {
        $sofa = $this->product('Canapé essai');
        $bed = $this->product('Lit essai')->addVariant((new ProductVariant())->setSize('160x200')->setInitialPrice('900.00')->setActualPrice('900.00'));
        $this->em->flush();
        $this->client->loginUser($this->seller);

        $values = $this->client->request('GET', '/admin/devis/new')->filter('#form-devis')->form()->getPhpValues();
        $values['quote']['customer'] = $this->customer->getId();
        $values['quote']['lines'] = [
            $this->line('Canapé essai', '1590.00', '0', 'percent', ['product' => $sofa->getId(), 'variant' => $bed->getVariants()->first()->getId()]),
            $this->line('Produit disparu', '100.00', '0', 'percent', ['product' => '999999999']),
        ];
        $this->client->request('POST', '/admin/devis/new', $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#form-devis', "Cette variante n'appartient pas au produit choisi.");
        self::assertSelectorTextContains('#form-devis', "Ce produit n'existe plus dans le catalogue.");
    }

    public function testInvalidAmountsAreRefused(): void
    {
        $this->client->loginUser($this->seller);

        $values = $this->client->request('GET', '/admin/devis/new')->filter('#form-devis')->form()->getPhpValues();
        $values['quote']['customer'] = $this->customer->getId();
        $values['quote']['lines'] = [
            $this->line('Ligne test', '-5.00', '150', 'percent'),
            $this->line('Canapé', '2400.00', '2400.01', 'final'),   // prix final au-dessus du prix
        ];
        $this->client->request('POST', '/admin/devis/new', $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#form-devis', 'Le prix final ne peut pas dépasser le prix unitaire.');
        self::assertSelectorNotExists('select[name="quote[discountType]"] option[value=final]');   // pas de prix final pour la remise globale
        self::assertSelectorTextContains('#form-devis', 'Le prix ne peut pas être négatif.');
        self::assertSelectorTextContains('#form-devis', 'Une remise ne peut pas dépasser 100 %.');
        self::assertSelectorTextContains('#form-devis', 'Indiquez le délai de livraison');
    }

    // Canapé à 2 400 € cédé 2 000 € : le client voit le prix d'origine, la remise en € et en %, et le total
    public function testFinalPriceOnTheDocuments(): void
    {
        $quote = $this->quote(QuoteStatus::Accepted)->addLine((new QuoteLine())->setLabel('Canapé')->setUnitPrice('2400.00')
            ->setDiscountType(DiscountType::FinalPrice)->setDiscountValue('2000.00'));
        $invoice = new Invoice($quote);
        $this->em->persist($invoice);
        $this->em->flush();
        $this->client->loginUser($this->seller);

        $this->client->request('GET', '/admin/devis/' . $quote->getId());
        self::assertSelectorTextContains('.lignes tbody tr:last-child', "2\u{A0}400,00\u{A0}€ -400,00\u{A0}€ soit -16,67\u{A0}% 2\u{A0}000,00\u{A0}€");

        $this->client->request('GET', '/admin/facture/' . $invoice->getId());
        self::assertSelectorTextContains('.lignes tbody tr:nth-child(2)', "2\u{A0}000,00\u{A0}€ -333,33\u{A0}€ soit 16,67\u{A0}% 1\u{A0}666,67\u{A0}€");
    }

    // Sans aucune remise, le devis et la facture n'en parlent pas : ni colonne, ni ligne « Remise globale »
    public function testNoDiscountColumnWithoutDiscount(): void
    {
        $quote = $this->quote(QuoteStatus::Accepted)->addLine((new QuoteLine())->setLabel('Canapé')->setUnitPrice('2400.00'));
        $invoice = new Invoice($quote);
        $this->em->persist($invoice);
        $this->em->flush();
        $this->client->loginUser($this->seller);

        $this->client->request('GET', '/admin/devis/' . $quote->getId());
        self::assertSelectorTextNotContains('.lignes thead', 'Remise');
        self::assertSelectorTextNotContains('.totaux', 'Remise globale');

        $this->client->request('GET', '/admin/facture/' . $invoice->getId());
        self::assertSelectorTextNotContains('.lignes thead', 'Remise');
        self::assertSelectorExists('.lignes tfoot th[colspan="3"]');
    }

    // Un acompte saisi ne dépasse jamais le total, même quand le total baisse après coup
    public function testDepositAmountStaysWithinTheTotal(): void
    {
        $quote = $this->quote(QuoteStatus::Sent)->setDepositAmount('80.00')->setLeadTime('6 semaines');   // ligne à 100 €
        $this->em->flush();
        $this->client->loginUser($this->seller);
        $values = $this->client->request('GET', '/admin/devis/new')->filter('#form-devis')->form()->getPhpValues();
        $values['quote'] = ['customer' => $this->customer->getId(), 'leadTime' => '6 semaines', 'depositAmount' => '100.01',
            'lines' => [$this->line('Ligne test', '100.00', '0', 'percent')]] + $values['quote'];
        $this->client->request('POST', '/admin/devis/new', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#form-devis', "L'acompte ne peut pas dépasser le total TTC du devis.");

        // Le total passe de 100 € à 50 €, sous l'acompte de 80 € déjà saisi
        $values = $this->client->request('GET', '/admin/devis/' . $quote->getId() . '/edit')->filter('#form-devis')->form()->getPhpValues();
        $values['quote']['lines'][0]['unitPrice'] = '50.00';
        $this->client->request('POST', '/admin/devis/' . $quote->getId() . '/edit', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#form-devis', "L'acompte ne peut pas dépasser le total TTC du devis.");
    }

    public function testDepositAmountOnTheDocuments(): void
    {
        $quote = (new Quote())->setNumber('BHD-2000-01-900')->setStatus(QuoteStatus::Accepted)->setCustomer($this->customer)
            ->setSeller($this->seller)->setLeadTime('6 semaines')->setDepositAmount('1000.00')
            ->addLine((new QuoteLine())->setLabel('Ensemble')->setUnitPrice('3081.00'));
        $invoice = new Invoice($quote);
        $this->em->persist($quote);
        $this->em->persist($invoice);
        $this->em->flush();
        $this->client->loginUser($this->seller);

        $this->client->request('GET', '/admin/devis/' . $quote->getId());
        self::assertSelectorTextContains('.conditions', "Acompte de 1\u{A0}000,00\u{A0}€ à la signature");
        self::assertSelectorTextContains('.totaux .acompte th', "Acompte 32,46\u{A0}% à la commande");
        self::assertSelectorTextContains('.totaux .acompte td', "1\u{A0}000,00\u{A0}€");
        self::assertSelectorTextContains('.totaux .solde', "2\u{A0}081,00\u{A0}€");

        $crawler = $this->client->request('GET', '/admin/facture/' . $invoice->getId());
        self::assertSame(["833,33\u{A0}€", "166,67\u{A0}€", "1\u{A0}000,00\u{A0}€"], $crawler->filter('.encadre-acompte td')->each(fn ($td) => $td->text()));   // HT, TVA, TTC
        self::assertSelectorTextContains('.objet', "Acompte de 1\u{A0}000,00\u{A0}€ versé à la commande");
        self::assertSelectorTextContains('.reste', "2\u{A0}081,00\u{A0}€ TTC");
    }

    public function testDeliveryAndAssemblyOnTheDocuments(): void
    {
        $quote = $this->quote(QuoteStatus::Accepted)->setDeliveryFee('120.00')->setAssemblyFee('60.00');   // rien de coché
        $invoice = new Invoice($quote);
        $this->em->persist($invoice);
        $this->em->flush();
        $this->client->loginUser($this->seller);
        $visibleTotals = fn ($crawler) => $crawler->filter('.totaux th')->each(fn ($th) => $th->text());

        $crawler = $this->client->request('GET', '/admin/devis/' . $quote->getId());
        self::assertSelectorTextContains('.parties', 'Retrait au showroom');
        self::assertNotContains('Livraison', $visibleTotals($crawler));
        self::assertNotContains('Montage', $visibleTotals($crawler));

        // Livraison offerte et montage payant : une ligne chacun
        $this->em->find(Quote::class, $quote->getId())->setDelivery(true)->setDeliveryFee('0.00')->setAssembly(true);
        $this->em->flush();
        $crawler = $this->client->request('GET', '/admin/devis/' . $quote->getId());
        self::assertSelectorTextContains('.parties', 'Livraison et montage à cette adresse');
        self::assertSame(['offerte', "60,00\u{A0}€"], $crawler->filter('[data-total=delivery], [data-total=assembly]')->each(fn ($td) => $td->text()));
        self::assertContains('Montage', $visibleTotals($crawler));

        $crawler = $this->client->request('GET', '/admin/facture/' . $invoice->getId());
        self::assertSame(['Livraison offerte', 'Montage'], $crawler->filter('.lignes tbody tr td:first-child')->reduce(fn ($td) => in_array(explode(' ', $td->text())[0], ['Livraison', 'Montage'], true))->each(fn ($td) => $td->text()));
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

    // Double clic sur « Émettre la facture » : la seconde requête attend que la première ait fini,
    // puis la relit déjà émise. Sans le verrou, chacune prenait un numéro et le premier était perdu.
    public function testSecondIssueWaitsForTheFirstOne(): void
    {
        $invoice = new Invoice($this->quote(QuoteStatus::Accepted));
        $this->em->persist($invoice);
        $this->em->flush();
        $invoices = self::getContainer()->get(InvoiceRepository::class);

        // Première requête : la facture relue sous verrou et numérotée, transaction pas encore terminée
        $this->em->wrapInTransaction(function () use ($invoices, $invoice) {
            $invoices->lock($invoice);
            $invoice->issue('BHF-2000-01-001', new \DateTimeImmutable());
        });

        // Seconde requête : une autre application, donc une autre connexion à la base
        $other = self::createKernel();
        $other->boot();
        $connection = $other->getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');   // elle attend 1 seconde au lieu de 50
        $connection->beginTransaction();

        try {
            $this->expectException(LockWaitTimeoutException::class);
            $other->getContainer()->get('test.service_container')->get(InvoiceRepository::class)
                ->lock($other->getContainer()->get('doctrine')->getManager()->getReference(Invoice::class, $invoice->getId()));
        } finally {
            $connection->rollBack();
            $other->shutdown();
        }
    }

    private function searchProducts(string $search): array
    {
        $this->client->request('GET', '/admin/devis/produits?q=' . urlencode($search));
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true);
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
