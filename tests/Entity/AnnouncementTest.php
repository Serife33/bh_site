<?php

namespace App\Tests\Entity;

use App\Entity\Announcement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AnnouncementTest extends TestCase
{
    #[DataProvider('links')]
    public function testLinkMustBeASitePathOrHttps(string $link, bool $valid): void
    {
        $violations = $this->validator()->validateProperty((new Announcement())->setLink($link), 'link');

        self::assertSame($valid, count($violations) === 0);
    }

    public static function links(): iterable
    {
        yield 'page du site' => ['/offres', true];
        yield 'adresse https' => ['https://www.instagram.com/brillancehome', true];
        yield 'javascript' => ['javascript:alert(1)', false];
        yield 'data' => ['data:text/html,<script>alert(1)</script>', false];
        yield 'http' => ['http://example.com', false];
        yield 'autre site sans schéma' => ['//example.com', false];
        yield 'autre site par la barre oblique inverse' => ['/\example.com', false];
        yield 'espace' => ['/offres javascript:alert(1)', false];
    }

    public function testEndCannotPrecedeStart(): void
    {
        $announcement = (new Announcement())->setStartDate(new \DateTimeImmutable('2026-11-30'))->setEndDate(new \DateTimeImmutable('2026-11-27'));
        $violations = $this->validator()->validateProperty($announcement, 'endDate');

        self::assertSame('La date de fin ne peut pas précéder la date de début.', $violations[0]->getMessage());
    }

    // Les dates arrivent de la base dans le fuseau du serveur, le jour est celui de Paris : seul le jour compte
    #[DataProvider('statuses')]
    public function testStatusOnTheSixthOfNovember(?string $start, ?string $end, bool $visible, string $status): void
    {
        $announcement = (new Announcement())
            ->setStartDate($start ? new \DateTimeImmutable($start, new \DateTimeZone('UTC')) : null)
            ->setEndDate($end ? new \DateTimeImmutable($end, new \DateTimeZone('UTC')) : null)
            ->setIsVisible($visible);

        self::assertSame($status, $announcement->getStatus(new \DateTimeImmutable('2026-11-06', new \DateTimeZone('Europe/Paris'))));
    }

    public static function statuses(): iterable
    {
        yield 'permanent' => [null, null, true, 'En ligne'];
        yield 'premier et dernier jour' => ['2026-11-06', '2026-11-06', true, 'En ligne'];
        yield 'commence demain' => ['2026-11-07', null, true, 'Programmé'];
        yield 'fini hier' => [null, '2026-11-05', true, 'Terminé'];
        yield 'masqué pendant sa période' => ['2026-11-01', '2026-11-30', false, 'Masqué'];
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
