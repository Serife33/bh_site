<?php

namespace App\Tests\Controller;

use App\Entity\Category;
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
}
