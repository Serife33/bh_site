<?php

namespace App\Tests\Service;

use App\Service\CsvReader;
use PHPUnit\Framework\TestCase;

final class CsvReaderTest extends TestCase
{
    public function testRowsAreIndexedByColumnNameAndBlankLinesSkipped(): void
    {
        // Enregistré par Excel : marque BOM en tête, fins de ligne Windows, une ligne vide
        $rows = (new CsvReader())->read($this->file("\xEF\xBB\xBFtaille;prix_ttc;note\r\n90 X 190;1050;PRIX DE TEST\r\n\r\n100Q;470;\r\n"), ['taille', 'prix_ttc']);

        self::assertSame([
            ['taille' => '90 X 190', 'prix_ttc' => '1050', 'note' => 'PRIX DE TEST'],
            ['taille' => '100Q', 'prix_ttc' => '470', 'note' => ''],
        ], $rows);
    }

    public function testMissingColumnIsRefused(): void
    {
        $this->expectExceptionMessage('colonnes manquantes (prix_ttc)');

        (new CsvReader())->read($this->file("taille;prix\n90 X 190;1050\n"), ['taille', 'prix_ttc']);
    }

    public function testLineWithWrongFieldCountIsRefused(): void
    {
        $this->expectExceptionMessage('ligne 3 : 3 champs au lieu de 2');

        (new CsvReader())->read($this->file("taille;prix_ttc\n90 X 190;1050\n100Q;470;oubli\n"), ['taille', 'prix_ttc']);
    }

    // Le contenu passé en adresse data: : aucun fichier temporaire à nettoyer
    private function file(string $content): string
    {
        return 'data://text/plain;base64,' . base64_encode($content);
    }
}
