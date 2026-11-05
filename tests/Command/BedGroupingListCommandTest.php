<?php

namespace App\Tests\Command;

use App\Entity\Category;
use App\Entity\Family;
use App\Entity\Media;
use App\Entity\Product;
use App\Enum\ProductModular;
use App\Enum\ProductSide;
use App\Service\CsvReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

// Un modèle en deux places et en une place, une taille masquée, et un modèle à deux fiches pour la même taille
final class BedGroupingListCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private string $file;
    private Category $category;

    protected function setUp(): void
    {
        $this->em = self::bootKernel()->getContainer()->get('doctrine')->getManager();
        $this->em->beginTransaction();   // annulée dans tearDown : le test ne laisse rien en base
        $this->file = sys_get_temp_dir() . '/liste-lits-test-' . uniqid() . '.csv';

        $this->category = (new Category())->setName('Literie');
        $this->em->persist($this->category);
        $model = (new Family())->setName('TESTLIT');
        $twice = (new Family())->setName('DOUBLON');
        $this->em->persist($model);
        $this->em->persist($twice);

        $this->product('Lit coffre TESTLIT 140x190', $model);
        $this->product('Lit coffre TESTLIT 160x200', $model, 'Testlit - Lit coffre 160x200 à bandes | Brillance Home', ['face.jpg', 'coffre.jpg']);
        $this->product('Lit coffre TESTLIT 180x200', $model)->setIsActive(false);
        $this->product('Lit coffre TESTLIT SINGLE 100x190', $model);
        $this->product('Lit coffre TESTLIT SINGLE 90x190', $model, 'Testlit Single - Lit coffre 90x190 en arc | Brillance Home');
        $this->product('Lit coffre DOUBLON 160x200', $twice);
        $this->product('Lit coffre DOUBLON 160X200 bis', $twice);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        @unlink($this->file);
        parent::tearDown();
    }

    public function testListFollowsSerifesRules(): void
    {
        $output = $this->list();

        // La fiche gardée et sa taille d'abord : 160x200 en deux places, 90x190 en une place
        self::assertSame([
            ['160x200', 'Lit coffre TESTLIT', 'lit-coffre-testlit', 'Testlit - Lit coffre à bandes | Brillance Home', 'face.jpg', 'coffre.jpg'],
            ['140x190', '', '', '', '', ''],
            ['90x190', 'Lit coffre TESTLIT SINGLE', 'lit-coffre-testlit-single', 'Testlit Single - Lit coffre en arc | Brillance Home', '', ''],
            ['100x190', '', '', '', '', ''],
        ], array_map(static fn (array $row) => [$row['taille'], $row['nouveau_nom'], $row['nouvelle_adresse'], $row['titre_google'], $row['photo_1'], $row['photo_2']], $this->rows()));

        self::assertStringContainsString('Lit coffre TESTLIT 180x200', $output);
        self::assertStringContainsString('masquée, laissée hors de la liste', $output);
        self::assertStringContainsString('DOUBLON deux places : plusieurs fiches en 160x200, groupe laissé hors de la liste', $output);
    }

    // Les choix faits à la main dans la liste (photos reconnues à l'œil, nom corrigé) survivent à une nouvelle liste
    public function testChoicesSurviveANewList(): void
    {
        $this->list();
        $rows = $this->rows();
        $rows[0] = [...$rows[0], 'nouveau_nom' => 'Lit coffre TESTLIT corrigé', 'photo_1' => 'coffre.jpg', 'photo_2' => 'face.jpg'];
        $file = new \SplFileObject($this->file, 'w');
        foreach ([array_keys($rows[0]), ...$rows] as $line) {
            $file->fputcsv($line, ';', '"', '');
        }
        $file = null;

        $this->list();

        self::assertSame(['Lit coffre TESTLIT corrigé', 'coffre.jpg', 'face.jpg'], [$this->rows()[0]['nouveau_nom'], $this->rows()[0]['photo_1'], $this->rows()[0]['photo_2']]);
    }

    private function list(): string
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:liste-lits'));
        $tester->execute(['fichier' => $this->file]);

        return $tester->getDisplay();
    }

    private function rows(): array
    {
        return (new CsvReader())->read($this->file, ['taille']);
    }

    private function product(string $name, Family $family, ?string $metaTitle = null, array $photos = []): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setSlug(Product::slugFrom($name))
            ->setMetaTitle($metaTitle)
            ->setCategory($this->category)
            ->setFamily($family)
            ->setInitialPrice('500')
            ->setActualPrice('500')
            ->setStock(0)
            ->setIsCustomMade(false)
            ->setIsModular(ProductModular::No)
            ->setSideLr(ProductSide::None)
            ->setPosition(1)
            ->setIsActive(true);
        $this->em->persist($product);

        foreach ($photos as $index => $photo) {
            $media = (new Media())->setUrl($photo)->setPosition($index + 1);
            $product->addMedium($media);
            $this->em->persist($media);
        }

        return $product;
    }
}
