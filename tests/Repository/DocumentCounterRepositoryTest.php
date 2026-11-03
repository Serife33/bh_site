<?php

namespace App\Tests\Repository;

use App\Repository\DocumentCounterRepository;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DocumentCounterRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DocumentCounterRepository $counters;

    protected function setUp(): void
    {
        $this->em = self::bootKernel()->getContainer()->get('doctrine')->getManager();
        $this->em->beginTransaction();   // annulée dans tearDown : le test ne laisse rien en base
        $this->counters = self::getContainer()->get(DocumentCounterRepository::class);
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        parent::tearDown();
    }

    public function testNumbersFollowEachOtherAndRestartEachMonth(): void
    {
        self::assertSame('BHD-2026-11-001', $this->counters->next('BHD', new \DateTimeImmutable('2026-11-02')));
        self::assertSame('BHD-2026-11-002', $this->counters->next('BHD', new \DateTimeImmutable('2026-11-30')));
        self::assertSame('BHD-2026-12-001', $this->counters->next('BHD', new \DateTimeImmutable('2026-12-01')));
        self::assertSame('BHF-2026-11-001', $this->counters->next('BHF', new \DateTimeImmutable('2026-11-03')));   // les factures ont leur propre compteur
    }

    // Deux vendeurs enregistrent en même temps : le second attend que le premier ait fini, il ne reçoit jamais le même numéro
    public function testSecondSellerWaitsUntilTheFirstQuoteIsSaved(): void
    {
        $this->counters->next('BHD', new \DateTimeImmutable('2026-11-02'));   // transaction du premier vendeur, pas encore validée

        // Le second vendeur : une autre application, donc une autre connexion à la base
        $other = self::createKernel();
        $other->boot();
        $connection = $other->getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');   // il attend 1 seconde au lieu de 50
        $connection->beginTransaction();

        try {
            $this->expectException(LockWaitTimeoutException::class);
            $other->getContainer()->get('test.service_container')->get(DocumentCounterRepository::class)
                ->next('BHD', new \DateTimeImmutable('2026-11-02'));
        } finally {
            $connection->rollBack();
            $other->shutdown();
        }
    }
}
