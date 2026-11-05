<?php

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Family;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductModular;
use App\Enum\ProductSide;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CatalogControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Le même noyau pour toutes les requêtes, donc la même connexion : la transaction les couvre toutes
        $this->client->disableReboot();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->em->beginTransaction();   // annulée dans tearDown : le test ne laisse rien en base
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        parent::tearDown();
    }

    // Décochée « Visible sur le site » : hors du menu, de « Nos univers » et du plan du site, et sa page ne s'ouvre plus
    public function testHiddenCategoryLeavesTheSite(): void
    {
        $this->em->persist((new Category())->setName('Catégorie visible test')->setSlug('categorie-visible-test'));
        $this->em->persist((new Category())->setName('Catégorie masquée test')->setSlug('categorie-masquee-test')->setIsVisible(false));
        $this->em->flush();

        $this->client->request('GET', '/');
        self::assertSelectorExists('a[href="/categorie/categorie-visible-test"]');
        self::assertSelectorNotExists('a[href="/categorie/categorie-masquee-test"]');

        $this->client->request('GET', '/categorie/categorie-masquee-test');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/categorie/categorie-visible-test');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/sitemap.xml');
        $plan = $this->client->getResponse()->getContent();
        self::assertStringContainsString('/categorie/categorie-visible-test', $plan);
        self::assertStringNotContainsString('/categorie/categorie-masquee-test', $plan);
    }

    // Une fiche regroupée dans une variante : son ancienne adresse redirige en 301 vers sa taille, et seule la nouvelle est dans le plan du site
    public function testLegacyAddressRedirectsToItsSize(): void
    {
        $category = (new Category())->setName('Catégorie lits test')->setSlug('categorie-lits-test');
        $this->em->persist($category);
        $this->product('Lit test', 'lit-test', $category, true)
            ->addVariant($this->variant('140x190', 'lit-test-140x190'))
            ->addVariant($this->variant('160x200', 'lit-test-160x200'));
        $this->product('Lit test 160x200', 'lit-test-160x200', $category, false);   // l'ancienne fiche, masquée
        $this->product('Lit masqué test', 'lit-masque-test', $category, false)
            ->addVariant($this->variant('90x190', 'lit-masque-test-90x190'));
        $this->em->flush();

        $this->client->request('GET', '/produit/lit-test-140x190');
        self::assertResponseRedirects('/produit/lit-test?taille=140x190', 301);

        $this->client->request('GET', '/produit/lit-test-160x200');
        self::assertResponseRedirects('/produit/lit-test?taille=160x200', 301);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.taille-opt.is-active', '160x200');

        // Fiche d'arrivée masquée : pas de redirection vers une page introuvable
        $this->client->request('GET', '/produit/lit-masque-test-90x190');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/sitemap.xml');
        $plan = $this->client->getResponse()->getContent();
        self::assertStringContainsString('/produit/lit-test</loc>', $plan);
        self::assertStringNotContainsString('/produit/lit-test-', $plan);
    }

    // Le bloc « Configuration » relie les fiches d'une famille, sauf sur un produit à variantes, qui a son propre choix
    public function testConfigurationBlockIsHiddenOnAProductWithVariants(): void
    {
        $category = (new Category())->setName('Catégorie configuration test')->setSlug('categorie-configuration-test');
        $family = (new Family())->setName('FAMILLE TEST');
        $this->em->persist($category);
        $this->em->persist($family);
        $this->product('Lit famille test', 'lit-famille-test', $category, true)->setFamily($family)
            ->addVariant($this->variant('160x200', 'lit-famille-test-160x200'));
        $this->product('Lit famille test SINGLE', 'lit-famille-test-single', $category, true)->setFamily($family);
        $this->em->flush();

        $this->client->request('GET', '/produit/lit-famille-test');
        self::assertSelectorTextNotContains('.product-info', 'Configuration');

        $this->client->request('GET', '/produit/lit-famille-test-single');
        self::assertSelectorTextContains('.product-info', 'Configuration');
    }

    private function product(string $name, string $slug, Category $category, bool $active): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setSlug($slug)
            ->setCategory($category)
            ->setInitialPrice('100')
            ->setActualPrice('100')
            ->setStock(0)
            ->setIsCustomMade(false)
            ->setIsModular(ProductModular::No)
            ->setSideLr(ProductSide::None)
            ->setPosition(1)
            ->setIsActive($active);
        $this->em->persist($product);

        return $product;
    }

    private function variant(string $size, string $legacySlug): ProductVariant
    {
        return (new ProductVariant())->setSize($size)->setInitialPrice('800')->setActualPrice('800')->setLegacySlug($legacySlug);
    }
}
