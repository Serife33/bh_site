<?php

namespace App\Tests\Command;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

// Les tableurs piégés, essayés à blanc sur une extraction minimale fabriquée pour le test
final class ImportCatalogueCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private string $dir;

    protected function setUp(): void
    {
        $this->em = self::bootKernel()->getContainer()->get('doctrine')->getManager();
        $this->em->beginTransaction();   // annulée dans tearDown : le test ne laisse rien en base
        $this->em->persist((new Category())->setName('Catégorie import test'));
        $this->em->flush();

        // Une vraie photo, un texte déguisé en photo, et le tableur rangé à côté des dossiers
        $this->dir = sys_get_temp_dir() . '/import-test-' . uniqid();
        mkdir("$this->dir/photos", recursive: true);
        imagepng(imagecreatetruecolor(2, 2), "$this->dir/photos/table.png");
        file_put_contents("$this->dir/photos/faux.jpg", 'du texte');
        file_put_contents("$this->dir/gammes.csv", "gamme;finition;fichier_vignette\n");
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        (new Filesystem())->remove($this->dir);
        parent::tearDown();
    }

    public function testRealPhotoIsAccepted(): void
    {
        $output = $this->import(['Table import test;table.png']);

        self::assertStringContainsString('+ Table import test', $output);
    }

    #[DataProvider('trappedPhotos')]
    public function testTrappedPhotoIsRefused(string $photo, string $message): void
    {
        $this->expectExceptionMessage($message);

        $this->import(["Table import test;$photo"]);
    }

    public static function trappedPhotos(): iterable
    {
        yield 'sortie du dossier' => ['../vente.csv', 'hors de son dossier'];
        yield 'chemin absolu' => ['/etc/hosts', 'hors de son dossier'];
        yield 'texte déguisé en photo' => ['faux.jpg', 'pas une photo JPEG, PNG ou WebP'];
    }

    public function testTwoModelsWithTheSameAddressAreRefused(): void
    {
        $this->expectExceptionMessage("Deux modèles donnent l'adresse « table-import-test »");

        $this->import(['Table import test;table.png', 'Table import test;table.png']);
    }

    // Un modèle par élément (« nom;photo »), chacun vendu en une variante, importé à blanc
    private function import(array $models): string
    {
        $modelLines = $sheetLines = '';
        foreach ($models as $page => $model) {
            [$name, $photo] = explode(';', $model);
            $modelLines .= "$page;Modèle $page;$name;;$photo;\n";
            $sheetLines .= "$page;Modèle $page;Catégorie import test;;90x190;;;100;\n";
        }
        file_put_contents("$this->dir/modeles.csv", "id_page;nom_fournisseur;nom_propose_fr;notes_fr;fichier_photo;autres_photos\n$modelLines");
        file_put_contents("$this->dir/vente.csv", "id_page;nom_fournisseur;categorie;sous_categories;taille;gamme;matiere;prix_ttc;prix_barre_ttc\n$sheetLines");

        $tester = new CommandTester((new Application(self::$kernel))->find('app:import-catalogue'));
        $tester->execute(['tableur' => "$this->dir/vente.csv", 'fournisseur' => 'Essai', '--dry-run' => true]);

        return $tester->getDisplay();
    }
}
