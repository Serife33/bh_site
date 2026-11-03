<?php

namespace App\Tests;

use App\Entity\Category;
use App\Entity\Product;
use App\Enum\ProductModular;
use App\Enum\ProductSide;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductSlugTest extends KernelTestCase
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

    public function testDeuxProduitsDeMemeNomOntDesAdressesDifferentes(): void
    {
        $category = (new Category())->setName('Catégorie test');
        $this->em->persist($category);

        $first = $this->save('Produit test slug', $category);
        $second = $this->save('Produit test slug', $category);

        self::assertSame('produit-test-slug', $first->getSlug());
        self::assertSame('produit-test-slug-2', $second->getSlug());

        // Adresse vidée puis régénérée : le produit ne se gêne pas lui-même
        $second->setSlug('');
        $this->em->flush();
        self::assertSame('produit-test-slug-2', $second->getSlug());
    }

    private function save(string $name, Category $category): Product
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
            ->setIsActive(false);
        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }
}
