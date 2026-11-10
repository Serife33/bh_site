<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Fabric;
use App\Entity\Family;
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
        yield 'recherche de client' => ['/admin/devis/clients?q=exemple'];
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
        self::assertSelectorTextNotContains('.doc', 'vendeur-test@example.com'); // le vendeur n'est pas imprimé
        self::assertSelectorTextContains('.pied', 'SIRET 901 674 507 00016');   // l'identité légale de la société
        self::assertSelectorNotExists('.pied .ac');                         // plus rien à compléter dans le pied
        // Le reste se paie au showroom avant la livraison ; les moyens de paiement sont dans les CGV 6.3
        self::assertSelectorTextContains('.conditions', 'Reste à payer au showroom, avant la livraison. La livraison est planifiée une fois le paiement reçu.');
        self::assertSelectorTextNotContains('.conditions', 'Moyens de paiement');
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
        self::assertSame(['id' => $first->getId(), 'name' => 'Canapé ESSAIMADRID 01', 'price' => '1155.00', 'customMade' => true, 'hidden' => false, 'leadTime' => null,
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

    // Un produit masqué sur le site reste proposé (chiffré avant sa mise en ligne), et la recherche le dit
    public function testHiddenProductsAreProposedAndSaidSo(): void
    {
        $this->product('Table ESSAIDANTE')->setIsActive(false);
        $this->em->flush();
        $this->client->loginUser($this->seller);

        self::assertTrue($this->searchProducts('essaidante')[0]['hidden']);   // chiffrable avant sa mise en ligne
    }

    // Le client d'un devis se cherche par son nom ou son téléphone, comme un produit
    public function testCustomerSearchForQuotes(): void
    {
        $this->client->loginUser($this->seller);

        $this->client->request('GET', '/admin/devis/clients?q=' . urlencode('06 00 00 00 01'));
        $customers = json_decode($this->client->getResponse()->getContent(), true);

        self::assertContains(['id' => $this->customer->getId(), 'name' => 'Client Exemple', 'phone' => '06 00 00 00 01', 'email' => null,
            'address' => "1 rue de l'Exemple, 33000 Bordeaux", 'url' => '/admin/client/' . $this->customer->getId() . '/edit'], $customers);
        $this->client->request('GET', '/admin/devis/clients?q=e');
        self::assertSame('[]', $this->client->getResponse()->getContent());   // 2 lettres au moins
    }

    // Le client se cherche mot par mot, et par son téléphone tapé sans séparateurs
    public function testCustomerSearchByWordsAndPhoneDigits(): void
    {
        $this->em->persist((new Customer())->setLastName('Martin')->setFirstName('Claire')->setPhone('06.12.34.56.78'));
        $this->em->flush();
        $this->client->loginUser($this->seller);
        $names = function (string $search): array {
            $this->client->request('GET', '/admin/devis/clients?q=' . urlencode($search));

            return array_column(json_decode($this->client->getResponse()->getContent(), true), 'name');
        };

        // Prénom et nom dans les deux ordres, chaque mot n'importe où ; téléphone tapé sans ses espaces ni ses points
        self::assertSame(['Claire Martin'], $names('Claire Martin'));
        self::assertSame(['Claire Martin'], $names('martin  CLAIRE'));
        self::assertSame([], $names('Claire Exemple'));
        self::assertSame(['Client Exemple'], $names('0600'));
        self::assertSame(['Claire Martin'], $names('martin 0612'));
        // La liste des clients cherche de la même façon
        $this->client->request('GET', '/admin/client?q=' . urlencode('Martin Claire'));
        self::assertSelectorTextContains('tbody', 'Martin');
        self::assertSelectorTextNotContains('tbody', 'Exemple');
    }

    // Une ligne libre : le nom tapé dans le champ « Article », sans produit choisi, est le nom imprimé
    public function testFreeLineByTheArticleField(): void
    {
        $this->client->loginUser($this->seller);
        $values = $this->client->request('GET', '/admin/devis/new')->filter('#form-devis')->form()->getPhpValues();
        $values['quote'] = ['customer' => $this->customer->getId(), 'leadTime' => '6 semaines',
            'lines' => [$this->line('Tapis sur mesure 200x300', '480.00', '0', 'percent', ['product' => ''])]] + $values['quote'];
        $this->client->request('POST', '/admin/devis/new', $values);

        $line = $this->em->getRepository(Quote::class)->findOneBy(['customer' => $this->customer])->getLines()->first();
        self::assertNull($line->getProduct());
        self::assertSame('Tapis sur mesure 200x300', $line->getLabel());
        $this->client->request('GET', '/admin/devis/' . $line->getQuote()->getId());
        self::assertSelectorTextContains('.lignes tbody', 'Tapis sur mesure 200x300');
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
            $this->line('', '100.00', '0', 'percent'),
        ];
        $this->client->request('POST', '/admin/devis/new', $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#form-devis', "Le prix final ne peut pas dépasser le prix unitaire (2\u{A0}400,00\u{A0}€).");
        self::assertSelectorNotExists('[name="quote[discountType]"][value=final]');   // pas de prix final pour la remise globale
        // Le résumé en haut renvoie vers chaque champ ; le prix final refusé l'est sous le champ de la remise
        self::assertSelectorExists('#resume-erreurs [data-erreur-de="quote_lines_1_discountValue"]');
        self::assertSelectorExists('#quote_lines_1_discountValue_error1');
        self::assertSelectorExists('#quote_lines_1_discountValue[aria-invalid="true"]');
        self::assertSelectorExists('#quote_lines_2_label[aria-invalid="true"]');
        self::assertSelectorNotExists('#quote_lines_0_label[aria-invalid]');
        self::assertSelectorTextContains('#form-devis', 'Le prix ne peut pas être négatif.');
        self::assertSelectorTextContains('#form-devis', 'Une remise ne peut pas dépasser 100 %.');
        self::assertSelectorTextContains('#form-devis', 'Indiquez le délai de livraison');
    }

    // L'écran du devis est une vraie page ; sans article, le lien du résumé mène au bouton d'ajout
    public function testQuoteWithoutArticleLeadsToTheAddButton(): void
    {
        $this->client->loginUser($this->seller);
        $values = $this->client->request('GET', '/admin/devis/new')->filter('#form-devis')->form()->getPhpValues();
        self::assertSelectorExists('meta[name="turbo-visit-control"][content="reload"]');
        self::assertSelectorExists('#form-devis[data-turbo="false"]');

        $values['quote']['customer'] = $this->customer->getId();
        unset($values['quote']['lines']);
        $this->client->request('POST', '/admin/devis/new', $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#resume-erreurs a[href="#ajouter-article"]', 'Ajoutez au moins un article.');
        self::assertSelectorExists('#ajouter-article');
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
        $values['quote'] = ['customer' => $this->customer->getId(), 'leadTime' => '6 semaines', 'depositMode' => 'montant', 'depositAmount' => '100.01',
            'lines' => [$this->line('Ligne test', '100.00', '0', 'percent')]] + $values['quote'];
        $this->client->request('POST', '/admin/devis/new', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#form-devis', "L'acompte ne peut pas dépasser le total TTC.");

        // Le total passe de 100 € à 50 €, sous l'acompte de 80 € déjà saisi
        $values = $this->client->request('GET', '/admin/devis/' . $quote->getId() . '/edit')->filter('#form-devis')->form()->getPhpValues();
        $values['quote']['lines'][0]['unitPrice'] = '50.00';
        $this->client->request('POST', '/admin/devis/' . $quote->getId() . '/edit', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#form-devis', "L'acompte ne peut pas dépasser le total TTC.");
    }

    // « 30 % du total » choisi : un montant envoyé quand même (sans JavaScript) ne compte pas
    public function testDepositRateIgnoresAnAmountSentAnyway(): void
    {
        $this->client->loginUser($this->seller);
        $values = $this->client->request('GET', '/admin/devis/new')->filter('#form-devis')->form()->getPhpValues();
        $values['quote'] = ['customer' => $this->customer->getId(), 'leadTime' => '6 semaines', 'depositMode' => 'taux', 'depositAmount' => '50.00',
            'lines' => [$this->line('Ligne test', '100.00', '0', 'percent')]] + $values['quote'];
        $this->client->request('POST', '/admin/devis/new', $values);

        self::assertResponseRedirects();
        self::assertNull($this->em->getRepository(Quote::class)->findOneBy(['customer' => $this->customer])->getDepositAmount());
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

    // Composer un canapé : la recherche le propose en premier pour une gamme modulable, la fenêtre liste ses modules
    // actifs dans leur ordre, à 0, ou un de chacun de ceux de l'ensemble cherché ; ses tissus sont proposés
    public function testCompositionIsProposedForAModularRange(): void
    {
        ['family' => $family, 'ensemble' => $ensemble] = $this->modularRange();
        $this->client->loginUser($this->seller);

        $results = $this->searchProducts('essailuma');
        self::assertSame(['composition' => true, 'name' => 'ESSAILUMA', 'modules' => 3, 'url' => '/admin/devis/composition/' . $family->getId()], $results[0]);
        self::assertCount(1, array_filter($results, static fn (array $result) => isset($result['composition'])));
        self::assertSame('/admin/devis/composition/' . $family->getId() . '?ensemble=' . $ensemble->getId(), $this->searchProducts('essailuma 3 places')[0]['url']);
        self::assertArrayNotHasKey('composition', $this->searchProducts('table essailuma')[0]);   // pas une gamme modulable

        $modules = fn (string $url) => $this->client->request('GET', $url)->filter('.compo-module')
            ->each(static fn ($module) => $module->filter('.compo-nom')->text() . ' ' . $module->filter('[data-nombre]')->text());
        self::assertSame(['Module ESSAILUMA méridienne 0', 'Module ESSAILUMA angle 0', 'Module ESSAILUMA central 70 cm 0'], $modules($results[0]['url']));
        self::assertSelectorTextContains('#compo-titre', 'Composer un canapé ESSAILUMA');
        self::assertSelectorExists('#compo-tissus option[value="Chenille Essai, Terra"]');
        self::assertSame(['Module ESSAILUMA méridienne 1', 'Module ESSAILUMA angle 0', 'Module ESSAILUMA central 70 cm 1'],
            $modules('/admin/devis/composition/' . $family->getId() . '?ensemble=' . $ensemble->getId()));
    }

    // La composition ajoutée au devis (2 centraux 70 cm, un angle, une méridienne, un tissu commun changé sur l'angle) :
    // un article par module, le bon total, et sur le devis imprimable un titre et un sous-total, puis une ligne libre à part
    public function testCompositionOnTheQuoteAndItsDocument(): void
    {
        ['modules' => [$meridienne, $angle, $central]] = $this->modularRange();
        $this->client->loginUser($this->seller);

        $url = '/admin/devis/new?client=' . $this->customer->getId();
        $values = $this->client->request('GET', $url)->filter('#form-devis')->form()->getPhpValues();
        $module = fn (Product $product, int $quantity, string $finish) => $this->line($product->getName(), $product->getActualPrice(), '0', 'percent',
            ['product' => $product->getId(), 'quantity' => (string) $quantity, 'finish' => $finish]);
        $values['quote']['lines'] = [
            $module($meridienne, 1, 'Chenille Essai, Terra'),
            $module($angle, 1, 'Velours Essai, Bleu nuit'),
            $module($central, 2, 'Chenille Essai, Terra'),
            $this->line('Housse de protection', '49.90', '0', 'percent'),
        ];
        $values['quote']['leadTime'] = '6 semaines';
        $this->client->request('POST', $url, $values);

        $quote = $this->em->getRepository(Quote::class)->findOneBy(['customer' => $this->customer]);
        self::assertSame('2417.90', $quote->getTotal());   // 829 + 689 + 2 × 425 + 49,90
        self::assertSame(['Chenille Essai, Terra', 'Velours Essai, Bleu nuit', 'Chenille Essai, Terra', null],
            $quote->getLines()->map(static fn (QuoteLine $line) => $line->getFinish())->getValues());

        $this->client->request('GET', '/admin/devis/' . $quote->getId());
        self::assertSelectorCount(1, '.compo-titre');
        self::assertSelectorTextContains('.lignes tbody tr:first-child', 'Canapé ESSAILUMA, composition');
        self::assertSelectorTextSame('.compo-total td:last-child', "2\u{A0}368,00\u{A0}€");   // sous-total de la composition
        self::assertSelectorTextContains('.compo + tbody tr:first-child', 'Housse de protection');   // la ligne qui suit, hors de la composition
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

    // Une gamme modulable d'essai : trois modules actifs dans leur ordre d'affichage (l'angle fabriqué sur mesure), un module
    // masqué, un ensemble composé de la méridienne et du central, et une table de la même gamme
    private function modularRange(): array
    {
        $family = (new Family())->setName('ESSAILUMA');
        $fabric = (new Fabric())->setName('Chenille')->setReference('Essai')->addFabricColor((new FabricColor())->setName('Terra')->setHex('#B16042'));
        $this->em->persist($family);
        $this->em->persist($fabric);
        $range = static fn (Product $product, ProductModular $modular, int $position) => $product->setFamily($family)->setIsModular($modular)->setPosition($position);
        $modules = [
            $range($this->product('Module ESSAILUMA méridienne')->setActualPrice('829.00')->setIsCustomMade(false)->addFabric($fabric), ProductModular::Module, 10),
            $range($this->product('Module ESSAILUMA angle')->setActualPrice('689.00'), ProductModular::Module, 16),
            $range($this->product('Module ESSAILUMA central 70 cm')->setActualPrice('425.00')->setIsCustomMade(false), ProductModular::Module, 17),
        ];
        $range($this->product('Module ESSAILUMA pouf')->setIsActive(false), ProductModular::Module, 11);
        $ensemble = $range($this->product('Canapé ESSAILUMA 3 places')->addModule($modules[0])->addModule($modules[2]), ProductModular::Yes, 5);
        $range($this->product('Table ESSAILUMA'), ProductModular::No, 30);
        $this->em->flush();

        return ['family' => $family, 'modules' => $modules, 'ensemble' => $ensemble];
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
