<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use App\Repository\AnnouncementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AnnouncementControllerTest extends WebTestCase
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
        $this->client->request('GET', '/admin/announcement');

        self::assertResponseRedirects('/login');
    }

    public function testCreatedMessageScrollsOnTheSite(): void
    {
        $this->logIn();
        $this->client->request('GET', '/admin/announcement/new');
        $this->client->submitForm('Enregistrer', [
            'announcement[text]' => 'Message de test',
            'announcement[link]' => 'https://www.instagram.com/brillancehome',
        ]);

        self::assertResponseRedirects('/admin/announcement');
        $this->client->followRedirect();
        self::assertSelectorTextContains('tbody tr:contains("Message de test")', 'En ligne');

        // Un lien vers un autre site : nouvel onglet, sans accès à la page d'origine. Deux fois, pour la boucle sans couture.
        $this->client->request('GET', '/');
        self::assertSelectorCount(2, '.band-track span a[href="https://www.instagram.com/brillancehome"][target="_blank"][rel="noopener"]');
    }

    public function testJavascriptLinkIsRefused(): void
    {
        $this->logIn();
        $this->client->request('GET', '/admin/announcement/new');
        $this->client->submitForm('Enregistrer', [
            'announcement[text]' => 'Message piégé',
            'announcement[link]' => 'javascript:alert(1)',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Le lien doit commencer par « / »');
    }

    public function testNoVisibleMessageNoBand(): void
    {
        foreach (static::getContainer()->get(AnnouncementRepository::class)->findForIndex() as $announcement) {
            $announcement->setIsVisible(false);
        }
        $this->em->flush();

        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.band');
    }

    private function logIn(): void
    {
        $admin = (new AdminUser())->setEmail('test-bandeau@brillancehome.test')->setRoles(['ROLE_ADMIN'])->setPassword('sans objet : connexion par loginUser');
        $this->em->persist($admin);
        $this->em->flush();
        $this->client->loginUser($admin);
    }
}
