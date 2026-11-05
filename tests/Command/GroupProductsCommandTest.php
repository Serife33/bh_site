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

// Deux fiches d'un même lit, 140x190 et 160x200, regroupées dans la seconde
final class GroupProductsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private string $photosDir;
    private string $prefix;
    private Category $category;
    private Product $small;
    private Product $big;

    protected function setUp(): void
    {
        $this->em = self::bootKernel()->getContainer()->get('doctrine')->getManager();
        $this->em->beginTransaction();   // annulée dans tearDown : le test ne laisse rien en base

        // Les photos sont comparées par leur contenu : « commune » existe sur les deux fiches sous deux noms
        $this->photosDir = self::getContainer()->getParameter('kernel.project_dir') . '/public/uploads/products';
        $this->prefix = 'regroupe-test-' . uniqid();
        foreach (['commune-a' => 'commune', 'propre-a' => 'propre a', 'commune-b' => 'commune', 'propre-b1' => 'propre b1', 'propre-b2' => 'propre b2', 'autre' => 'autre'] as $name => $content) {
            file_put_contents("$this->photosDir/$this->prefix-$name.jpg", $content);
        }

        $this->category = (new Category())->setName('Catégorie regroupement test');
        $this->em->persist($this->category);
        $this->small = $this->product('Lit test 140x190', '900.00', '800.00', 2, ['commune-a', 'propre-a']);
        $this->big = $this->product('Lit test 160x200', '850.00', '850.00', 0, ['commune-b', 'propre-b1', 'propre-b2'])
            ->setDescription('Le lit test 160x200à tête capitonnée.');
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
        [$big, $small] = $this->reload();

        self::assertSame(
            ['Lit test', 'lit-test', 'Lit test | Brillance Home', 'Lit test : un lit coffre.', 'Le lit test à tête capitonnée.'],
            [$big->getName(), $big->getSlug(), $big->getMetaTitle(), $big->getMetaDescription(), $big->getDescription()],
        );
        // Prix et stock repris tels quels de chaque fiche ; la fiche affiche la moins chère et la somme des stocks
        self::assertSame([
            ['160x200', '850.00', '850.00', 0, 'lit-test-160x200'],
            ['140x190', '900.00', '800.00', 2, 'lit-test-140x190'],
        ], $big->getVariants()->map(static fn (ProductVariant $v) => [$v->getSize(), $v->getInitialPrice(), $v->getActualPrice(), $v->getStock(), $v->getLegacySlug()])->getValues());
        self::assertSame(['900.00', '800.00', 2], [$big->getInitialPrice(), $big->getActualPrice(), $big->getStock()]);

        // Les deux photos choisies en tête, puis les autres ; la photo propre à la petite fiche la rejoint, la photo commune reste sur la fiche masquée
        self::assertSame(['propre-b2', 'commune-b', 'propre-b1', 'propre-a'], $this->photoNames($big));
        self::assertSame(['commune-a'], $this->photoNames($small));
        self::assertTrue($big->isActive());
        self::assertFalse($small->isActive());
    }

    public function testSecondRunChangesNothingAndComparesWithTheList(): void
    {
        $this->group($this->rows());
        $this->em->clear();

        $tester = $this->group($this->rows());

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('= Lit test : déjà regroupé', $tester->getDisplay());
        self::assertMatchesRegularExpression('/Différences avec la liste\s+0/', $tester->getDisplay());
        self::assertCount(2, $this->reload()[0]->getVariants());
    }

    // Après une vente, ou une ancienne fiche remise en ligne par erreur, la relance le signale et échoue
    public function testSecondRunFailsWhenSomethingNoLongerMatchesTheList(): void
    {
        $this->group($this->rows());
        [$big, $small] = $this->reload();
        $big->getVariants()->findFirst(static fn (int $key, ProductVariant $v) => $v->getSize() === '140x190')->setStock(0);
        $small->setIsActive(true);
        $this->em->flush();

        $tester = $this->group($this->rows());

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('stock 0 en base, 2 dans la liste', $tester->getDisplay());
        self::assertStringContainsString('ancienne fiche de nouveau visible', $tester->getDisplay());
    }

    public function testDryRunWritesNothing(): void
    {
        $output = $this->group($this->rows(), ['--dry-run' => true])->getDisplay();
        [$big, $small] = $this->reload();

        self::assertStringContainsString('140x190 : 800.00 €, prix initial 900.00 €, stock 2, depuis lit-test-140x190', $output);
        self::assertStringContainsString('description Google : Lit test : un lit coffre.', $output);
        self::assertSame('Lit test 160x200', $big->getName());
        self::assertCount(0, $big->getVariants());
        self::assertTrue($small->isActive());
    }

    // Tout ou rien, en vrai passage : un second groupe faux, et le premier n'est pas écrit non plus
    public function testWrongSecondGroupLeavesTheFirstUntouched(): void
    {
        $other = $this->product('Lit autre 90x190', '400.00', '400.00', 0, ['autre']);
        $this->em->flush();
        $rows = [...$this->rows(), [
            'fiche_gardee' => $other->getId(), 'id' => $other->getId(), 'adresse' => 'lit-autre-90x190', 'taille' => '90x190', 'prix_initial' => '400', 'prix_actuel' => '390', 'stock' => '0',
            'nouveau_nom' => 'Lit autre', 'nouvelle_adresse' => 'lit-autre', 'titre_google' => '', 'description_google' => '', 'photo_1' => '', 'photo_2' => '',
        ]];

        try {
            $this->group($rows);
            self::fail('La liste fausse aurait dû être refusée.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Lit autre 90x190 a changé depuis la liste', $exception->getMessage());
        }

        [$big, $small] = $this->reload();
        self::assertSame('Lit test 160x200', $big->getName());
        self::assertCount(0, $big->getVariants());
        self::assertTrue($small->isActive());
    }

    public function testMaskedOriginIsRefused(): void
    {
        $this->small->setIsActive(false);
        $this->em->flush();

        $this->expectExceptionMessage('Lit test 140x190 est masquée');

        $this->group($this->rows(), ['--dry-run' => true]);
    }

    #[DataProvider('wrongLists')]
    public function testWrongListIsRefused(int $line, string $column, string $value, string $message): void
    {
        $rows = $this->rows();
        $rows[$line][$column] = $value;

        $this->expectExceptionMessage($message);

        $this->group($rows, ['--dry-run' => true]);
    }

    public static function wrongLists(): iterable
    {
        yield 'prix changé depuis la liste' => [1, 'prix_actuel', '790', 'a changé depuis la liste : prix_actuel 800.00 en base, 790 dans la liste'];
        yield 'prix écrit à la française' => [1, 'prix_initial', '900,00', 'prix_initial invalide'];
        yield 'deux fiches pour une même taille' => [1, 'taille', '160x200', 'Une autre variante a déjà cette taille'];
        yield 'taille illisible' => [1, 'taille', '140*190', 'à écrire comme 140x190'];
        yield 'adresse qui ne correspond pas' => [1, 'adresse', 'lit-test-autre', 'son adresse est « lit-test-140x190 »'];
        yield 'titre Google sur une autre ligne' => [1, 'titre_google', 'Lit test bis', 'restent vides sur les autres lignes'];
        yield 'photo choisie absente de la fiche' => [0, 'photo_2', 'inconnue.jpg', 'photo choisie absente de la fiche : inconnue.jpg'];
    }

    // Les anciennes fiches ne partent qu'une fois le regroupement fait et vérifié ; la redirection reste
    public function testOldProductsAreDeletedOnlyOnceGroupedAndVerified(): void
    {
        try {
            $this->group($this->rows(), ['--supprime-anciennes' => true]);
            self::fail('La suppression aurait dû être refusée avant le regroupement.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Suppression refusée', $exception->getMessage());
        }
        $this->em->clear();

        $this->group($this->rows());
        $this->em->clear();
        $output = $this->group($this->rows(), ['--supprime-anciennes' => true])->getDisplay();

        self::assertStringContainsString('- Lit test 140x190 (lit-test-140x190) : supprimée', $output);
        self::assertNull($this->em->find(Product::class, $this->small->getId()));
        self::assertSame(['lit-test-160x200', 'lit-test-140x190'], $this->reload()[0]->getVariants()->map(static fn (ProductVariant $v) => $v->getLegacySlug())->getValues());
    }

    // Les deux lignes de la liste : la fiche gardée d'abord, puis l'autre taille
    private function rows(): array
    {
        $kept = $this->big->getId();
        $none = ['nouveau_nom' => '', 'nouvelle_adresse' => '', 'titre_google' => '', 'description_google' => '', 'photo_1' => '', 'photo_2' => ''];

        return [
            ['fiche_gardee' => $kept, 'id' => $kept, 'adresse' => 'lit-test-160x200', 'taille' => '160x200', 'prix_initial' => '850.00', 'prix_actuel' => '850.00', 'stock' => '0',
                'nouveau_nom' => 'Lit test', 'nouvelle_adresse' => 'lit-test', 'titre_google' => 'Lit test | Brillance Home', 'description_google' => 'Lit test : un lit coffre.',
                'photo_1' => "$this->prefix-propre-b2.jpg", 'photo_2' => "$this->prefix-commune-b.jpg"],
            ['fiche_gardee' => $kept, 'id' => $this->small->getId(), 'adresse' => 'lit-test-140x190', 'taille' => '140x190', 'prix_initial' => '900', 'prix_actuel' => '800', 'stock' => '2', ...$none],
        ];
    }

    private function group(array $rows, array $options = []): CommandTester
    {
        $file = sys_get_temp_dir() . "/$this->prefix.csv";
        file_put_contents($file, implode(';', array_keys($rows[0])) . "\n" . implode("\n", array_map(static fn (array $row) => implode(';', $row), $rows)) . "\n");

        try {
            $tester = new CommandTester((new Application(self::$kernel))->find('app:regroupe-fiches'));
            $tester->execute(['fichier' => $file, ...$options]);
        } finally {
            unlink($file);
        }

        return $tester;
    }

    // Les deux fiches relues en base
    private function reload(): array
    {
        $this->em->clear();

        return [$this->em->find(Product::class, $this->big->getId()), $this->em->find(Product::class, $this->small->getId())];
    }

    private function product(string $name, string $initialPrice, string $actualPrice, int $stock, array $photos): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setSlug(Product::slugFrom($name))
            ->setCategory($this->category)
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
