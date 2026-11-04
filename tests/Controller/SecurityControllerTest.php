<?php

namespace App\Tests\Controller;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// La limite d'essais de connexion, sur un administrateur créé pour le test
final class SecurityControllerTest extends WebTestCase
{
    private const EMAIL = 'limite-essais@test.local';
    private const PASSWORD = 'bon-mot-de-passe';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();   // un seul noyau : la transaction couvre toutes les requêtes
        $this->client->followRedirects();

        $container = static::getContainer();
        $container->get('cache.rate_limiter')->clear();   // compteur remis à zéro : le test peut se relancer aussitôt
        $this->em = $container->get('doctrine')->getManager();
        $this->em->beginTransaction();   // annulée dans tearDown : le test ne laisse rien en base

        $admin = (new AdminUser())->setEmail(self::EMAIL)->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($admin, self::PASSWORD));
        $this->em->persist($admin);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        parent::tearDown();
    }

    public function testRightPasswordOpensAdmin(): void
    {
        $this->login(self::PASSWORD);

        self::assertRouteSame('app_admin');
    }

    public function testSixthAttemptIsRefusedEvenWithRightPassword(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->login('mauvais-mot-de-passe');
            self::assertSelectorTextContains('.alert', 'Identifiants invalides.');
        }

        $this->login(self::PASSWORD);

        self::assertRouteSame('app_login');
        self::assertSelectorTextContains('.alert', 'Trop de tentatives de connexion échouées, veuillez réessayer dans');
    }

    // Un essai depuis le formulaire, comme dans le navigateur (jeton CSRF compris)
    private function login(string $password): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => self::EMAIL, '_password' => $password]);
    }
}
