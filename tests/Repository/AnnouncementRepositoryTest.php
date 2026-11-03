<?php

namespace App\Tests\Repository;

use App\Entity\Announcement;
use App\Repository\AnnouncementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Le filtrage par dates du bandeau, le 7 novembre, sur des messages créés pour le test
final class AnnouncementRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = self::bootKernel()->getContainer()->get('doctrine')->getManager();
        $this->em->beginTransaction();   // annulée dans tearDown : le test ne laisse rien en base
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        parent::tearDown();
    }

    public function testVisibleOnTheSeventhPromosFirst(): void
    {
        $day = new \DateTimeImmutable('2026-11-07', new \DateTimeZone('Europe/Paris'));
        $created = [
            $this->announcement('Test sans dates', null, null),
            $this->announcement('Test pendant, un seul jour', '2026-11-07', '2026-11-07')->setIsPromo(true)->setPosition(99),
            $this->announcement('Test avant', '2026-11-08', null),
            $this->announcement('Test après', null, '2026-11-06'),
            $this->announcement('Test masqué', null, null)->setIsVisible(false),
        ];
        $this->em->flush();

        $announcements = self::getContainer()->get(AnnouncementRepository::class)->findVisibleOn($day);
        // Les messages déjà en base restent : on ne regarde que ceux du test
        $texts = array_values(array_filter(
            array_map(static fn (Announcement $announcement) => $announcement->getText(), $announcements),
            static fn (string $text) => str_starts_with($text, 'Test '),
        ));

        self::assertSame(['Test pendant, un seul jour', 'Test sans dates'], $texts);

        // La règle SQL du site et l'état de l'admin (getStatus) disent la même chose pour chaque message
        foreach ($created as $announcement) {
            self::assertSame(in_array($announcement, $announcements, true), $announcement->getStatus($day) === 'En ligne', $announcement->getText());
        }
    }

    private function announcement(string $text, ?string $start, ?string $end): Announcement
    {
        $announcement = (new Announcement())
            ->setText($text)
            ->setStartDate($start ? new \DateTimeImmutable($start) : null)
            ->setEndDate($end ? new \DateTimeImmutable($end) : null);
        $this->em->persist($announcement);

        return $announcement;
    }
}
