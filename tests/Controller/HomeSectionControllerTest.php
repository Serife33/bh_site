<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Entity\Category;
use App\Entity\Product;
use App\Enum\ProductModular;
use App\Enum\ProductSide;
use App\Repository\HomeSectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class HomeSectionControllerTest extends WebTestCase
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

    public function testAdminRequiresLogin(): void
    {
        $this->client->request('GET', '/admin/home-section');

        self::assertResponseRedirects('/login');
    }

    public function testCreatedSectionShowsOnHomeInItsOrder(): void
    {
        $category = (new Category())->setName('Catégorie test');
        $this->em->persist($category);
        $chair = $this->product('Test chaise', $category);
        $table = $this->product('Test table', $category);
        $this->logIn();

        // Les cases cochées arrivent dans l'ordre de la liste (alphabétique) : chaise puis table
        $form = $this->client->request('GET', '/admin/home-section/new')->selectButton('Enregistrer')->form();
        $values = $form->getPhpValues();
        $values['home_section']['title'] = 'Test section';
        $values['home_section']['products'] = [$table->getId(), $chair->getId()];
        $this->client->request('POST', $form->getUri(), $values);

        self::assertResponseRedirects();
        $list = $this->client->followRedirect()->filter('#liste-ordre');
        self::assertSame(['Test chaise', 'Test table'], $list->filter('.ordre-nom')->each(static fn (Crawler $name) => $name->text()));

        // Le glisser-déposer met la table en premier
        $this->client->jsonRequest('POST', '/admin/home-section/produits/ordre', [
            'ids' => array_reverse($list->filter('li')->each(static fn (Crawler $line) => $line->attr('data-id'))),
            '_token' => $list->attr('data-token'),
        ]);
        self::assertResponseIsSuccessful();

        $section = $this->client->request('GET', '/')->filter('section.section')
            ->reduce(static fn (Crawler $section) => $section->filter('h2')->count() && $section->filter('h2')->text() === 'Test section');
        self::assertSame(['Test table', 'Test chaise'], $section->filter('.pcard-name')->each(static fn (Crawler $name) => $name->text()));
    }

    public function testEmptiedTitleIsRefused(): void
    {
        $section = static::getContainer()->get(HomeSectionRepository::class)->findForIndex()[0];
        $this->logIn();
        $this->client->request('GET', '/admin/home-section/' . $section->getId() . '/edit');
        $this->client->submitForm('Enregistrer', ['home_section[title]' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Le titre est obligatoire.');
    }

    public function testFixedBlocksStayWithoutSections(): void
    {
        foreach (static::getContainer()->get(HomeSectionRepository::class)->findForIndex() as $section) {
            $section->setIsVisible(false);
        }
        $this->em->flush();

        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.pcard');
        self::assertSelectorExists('.reassu');
        self::assertSelectorExists('section.dark');
    }

    private function product(string $name, Category $category): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setCategory($category)
            ->setInitialPrice('100')
            ->setActualPrice('100')
            ->setStock(0)
            ->setIsCustomMade(false)
            ->setIsModular(ProductModular::No)
            ->setSideLr(ProductSide::None)
            ->setPosition(1)
            ->setIsActive(true);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    private function logIn(): void
    {
        $admin = (new AdminUser())->setEmail('test-accueil@brillancehome.test')->setRoles(['ROLE_ADMIN'])->setPassword('sans objet : connexion par loginUser');
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);
    }
}
