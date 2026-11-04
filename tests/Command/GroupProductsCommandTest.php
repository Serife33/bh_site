<?php

namespace App\Tests\Command;

use App\Entity\Category;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductModular;
use App\Enum\ProductSide;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

// Deux fiches d'un même lit, 140x190 et 160x200, regroupées dans la seconde, qui a le plus de photos
final class GroupProductsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private string $photosDir;
    private string $prefix;
    private Product $small;
    private Product $big;

    protected function setUp(): void
    {
        $this->em = self::bootKernel()->getContainer()->get('doctrine')->getManager();
        $this->em->beginTransaction();   // annulée dans tearDown : le test ne laisse rien en base

        // Les photos sont comparées par leur contenu : « commune » existe sur les deux fiches sous deux noms
        $this->photosDir = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/products';
        $this->prefix = 'regroupe-test-' . uniqid();
        foreach (['commune-a' => 'commune', 'propre-a' => 'propre a', 'commune-b' => 'commune', 'propre-b1' => 'propre b1', 'propre-b2' => 'propre b2'] as $name => $content) {
            file_put_contents("$this->photosDir/$this->prefix-$name.jpg", $content);
        }

        $category = (new Category())->setName('Catégorie regroupement test');
        $this->em->persist($category);
        $this->small = $this->product('Lit test 140x190', '900.00', '800.00', 2, $category, ['commune-a', 'propre-a']);
        $this->big = $this->product('Lit test 160x200', '850.00', '850.00', 0, $category, ['commune-b', 'propre-b1', 'propre-b2'])
            ->setMetaTitle('Lit test 160x200à tête | Brillance Home');
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->rollback();
        (new Filesystem())->remove(glob("$this->photosDir/$this->prefix-*"));
        parent::tearDown();
    }

    public function testSizesBecomeVariantsOfTheKeptProduct(): void
    {
        $this->group($this->rows());
        $this->em->clear();
        $big = $this->em->find(Product::class, $this->big->getId());
        $small = $this->em->find(Product::class, $this->small->getId());

        self::assertSame(['Lit test', 'lit-test', 'Lit test à tête | Brillance Home'], [$big->getName(), $big->getSlug(), $big->getMetaTitle()]);
        // Prix et stock repris tels quels de chaque fiche ; la fiche affiche la moins chère et la somme des stocks
        self::assertSame([
            ['140x190', '900.00', '800.00', 2, 'lit-test-140x190'],
            ['160x200', '850.00', '850.00', 0, 'lit-test-160x200'],
        ], $big->getVariants()->map(static fn (ProductVariant $v) => [$v->getSize(), $v->getInitialPrice(), $v->getActualPrice(), $v->getStock(), $v->getLegacySlug()])->getValues());
        self::assertSame(['900.00', '800.00', 2], [$big->getInitialPrice(), $big->getActualPrice(), $big->getStock()]);

        // La photo propre à la petite fiche rejoint la grande ; la photo commune reste sur la fiche masquée
        self::assertSame(['commune-b', 'propre-b1', 'propre-b2', 'propre-a'], $this->photoNames($big));
        self::assertSame(['commune-a'], $this->photoNames($small));
        self::assertTrue($big->isActive());
        self::assertFalse($small->isActive());
    }

    public function testSecondRunChangesNothingAndComparesWithTheList(): void
    {
        $this->group($this->rows());
        $this->em->clear();

        $output = $this->group($this->rows());

        self::assertStringContainsString('= Lit test : déjà regroupé', $output);
        self::assertMatchesRegularExpression('/Différences avec la liste\s+0/', $output);
        self::assertCount(2, $this->em->find(Product::class, $this->big->getId())->getVariants());
    }

    public function testDryRunWritesNothing(): void
    {
        $output = $this->group($this->rows(), dryRun: true);
        $this->em->clear();

        self::assertStringContainsString('140x190 : 800.00 €, prix initial 900.00 €, stock 2, depuis lit-test-140x190', $output);
        $big = $this->em->find(Product::class, $this->big->getId());
        self::assertSame('Lit test 160x200', $big->getName());
        self::assertCount(0, $big->getVariants());
        self::assertTrue($this->em->find(Product::class, $this->small->getId())->isActive());
    }

    #[DataProvider('wrongLists')]
    public function testWrongListIsRefused(int $line, string $column, string $value, string $message): void
    {
        $rows = $this->rows();
        $rows[$line][$column] = $value;

        $this->expectExceptionMessage($message);

        $this->group($rows, dryRun: true);
    }

    public static function wrongLists(): iterable
    {
        yield 'prix changé depuis la liste' => [0, 'prix_actuel', '790', 'a changé depuis la liste : prix_actuel 800.00 en base, 790 dans la liste'];
        yield 'prix écrit à la française' => [0, 'prix_initial', '900,00', 'prix_initial invalide'];
        yield 'deux fiches pour une même taille' => [0, 'taille', '160x200', 'Une autre variante a déjà cette taille'];
        yield 'taille illisible' => [0, 'taille', '140*190', 'à écrire comme 140x190'];
        yield 'adresse qui ne correspond pas' => [0, 'adresse', 'lit-test-autre', 'son adresse est « lit-test-140x190 »'];
        yield 'nouveau nom sur une autre ligne' => [0, 'nouveau_nom', 'Lit test bis', 'vides ailleurs'];
    }

    // Les deux lignes de la liste, la fiche gardée en second
    private function rows(): array
    {
        $kept = $this->big->getId();

        return [
            ['fiche_gardee' => $kept, 'id' => $this->small->getId(), 'adresse' => 'lit-test-140x190', 'taille' => '140x190', 'prix_initial' => '900', 'prix_actuel' => '800', 'stock' => '2', 'nouveau_nom' => '', 'nouvelle_adresse' => ''],
            ['fiche_gardee' => $kept, 'id' => $kept, 'adresse' => 'lit-test-160x200', 'taille' => '160x200', 'prix_initial' => '850.00', 'prix_actuel' => '850.00', 'stock' => '0', 'nouveau_nom' => 'Lit test', 'nouvelle_adresse' => 'lit-test'],
        ];
    }

    private function group(array $rows, bool $dryRun = false): string
    {
        $file = sys_get_temp_dir() . "/$this->prefix.csv";
        file_put_contents($file, implode(';', array_keys($rows[0])) . "\n" . implode("\n", array_map(static fn (array $row) => implode(';', $row), $rows)) . "\n");

        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:regroupe-fiches'));
            $status = $tester->execute(['fichier' => $file, '--dry-run' => $dryRun]);
        } finally {
            unlink($file);
        }
        self::assertSame(Command::SUCCESS, $status);

        return $tester->getDisplay();
    }

    private function product(string $name, string $initialPrice, string $actualPrice, int $stock, Category $category, array $photos): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setSlug(Product::slugFrom($name))
            ->setCategory($category)
            ->setInitialPrice($initialPrice)
            ->setActualPrice($actualPrice)
            ->setStock($stock)
            ->setIsCustomMade(false)
            ->setIsModular(ProductModular::No)
            ->setSideLr(ProductSide::None)
            ->setPosition(1)
            ->setIsActive(true);
        $this->em->persist($product);

        foreach ($photos as $index => $photo) {
            $media = (new Media())->setUrl("$this->prefix-$photo.jpg")->setPosition($index + 1);
            $product->addMedium($media);
            $this->em->persist($media);
        }

        return $product;
    }

    private function photoNames(Product $product): array
    {
        return $product->getMedia()->map(fn (Media $media) => substr($media->getUrl(), strlen($this->prefix) + 1, -4))->getValues();
    }
}
