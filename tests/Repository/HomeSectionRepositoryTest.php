<?php

namespace App\Tests\Repository;

use App\Entity\Category;
use App\Entity\HomeSection;
use App\Entity\Product;
use App\Enum\ProductModular;
use App\Enum\ProductSide;
use App\Repository\HomeSectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// Ce que l'accueil reçoit : sections visibles dans leur ordre, chacune avec ses seuls produits visibles, modules exclus
final class HomeSectionRepositoryTest extends KernelTestCase
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

    public function testVisibleSectionsInOrderWithVisibleProductsOnly(): void
    {
        $category = (new Category())->setName('Catégorie test');
        $this->em->persist($category);
        $chair = $this->product('Test chaise', $category, true);
        $table = $this->product('Test table', $category, true);
        $hidden = $this->product('Test masqué', $category, false);
        $module = $this->product('Test module', $category, true)->setIsModular(ProductModular::Module);

        $this->section('Test deuxième', 99, true)->addProduct($table)->addProduct($hidden)->addProduct($module)->addProduct($chair);
        $this->section('Test première', 98, true)->addProduct($chair);
        $this->section('Test masquée', 97, false)->addProduct($chair);
        $this->section('Test sans produit visible', 96, true)->addProduct($hidden);
        $this->em->flush();
        $this->em->clear();   // sinon Doctrine rendrait les sections gardées en mémoire, avec tous leurs produits

        $sections = self::getContainer()->get(HomeSectionRepository::class)->findVisibleForHome();
        // Les sections déjà en base restent : on ne regarde que celles du test
        $names = [];
        foreach ($sections as $section) {
            if (str_starts_with($section->getTitle(), 'Test ')) {
                $names[$section->getTitle()] = array_map(static fn (Product $product) => $product->getName(), $section->getProducts());
            }
        }

        self::assertSame([
            'Test première' => ['Test chaise'],
            'Test deuxième' => ['Test table', 'Test chaise'],
        ], $names);
    }

    private function section(string $title, int $position, bool $visible): HomeSection
    {
        $section = (new HomeSection())->setTitle($title)->setPosition($position)->setIsVisible($visible);
        $this->em->persist($section);

        return $section;
    }

    private function product(string $name, Category $category, bool $active): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setCategory($category)
            ->setInitialPrice('100')
            ->setActualPrice('100')
            ->setStock(0)
            ->setIsCustomMade(false)
            ->setIsModular(ProductModular::No)
            ->setSideLr(ProductSide::None)
            ->setPosition(1)
            ->setIsActive($active);
        $this->em->persist($product);

        return $product;
    }
}
