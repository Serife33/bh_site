<?php

namespace App\Tests\Entity;

use App\Entity\Product;
use App\Entity\ProductVariant;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

final class ProductTest extends TestCase
{
    public function testWithoutVariantsPriceAndStockStay(): void
    {
        $product = $this->product();
        $product->refreshFromVariants();

        self::assertSame('1200.00', $product->getInitialPrice());
        self::assertSame('1100.00', $product->getActualPrice());
        self::assertSame(4, $product->getStock());
    }

    public function testSummaryTakesCheapestVariantAndSumsStocks(): void
    {
        $product = $this->product()
            ->addVariant((new ProductVariant())->setInitialPrice('1300.00')->setActualPrice('1155.00')->setStock(2))
            ->addVariant((new ProductVariant())->setInitialPrice('1100.00')->setActualPrice('990.00')->setStock(3));
        $product->refreshFromVariants();

        self::assertSame('990.00', $product->getActualPrice());
        self::assertSame('1100.00', $product->getInitialPrice()); // le prix barré de la variante à 990 €
        self::assertSame(5, $product->getStock());
    }

    public function testVariantsFollowProductRulesAndStayDistinct(): void
    {
        $product = $this->product()
            ->addVariant($this->variant('160x200'))
            ->addVariant($this->variant('160X200'))                          // même taille, même matière (aucune)
            ->addVariant($this->variant('180x200')->setActualPrice('-5.00')) // prix négatif
            ->addVariant($this->variant(null));                              // ni taille ni matière

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($product);
        $paths = array_map(static fn (ConstraintViolationInterface $violation) => $violation->getPropertyPath(), iterator_to_array($violations));

        self::assertSame(
            ['variants[1].size', 'variants[2].actualPrice', 'variants[3]'],
            array_values(array_filter($paths, static fn (string $path) => str_starts_with($path, 'variants'))),
        );
    }

    private function variant(?string $size): ProductVariant
    {
        return (new ProductVariant())->setSize($size)->setInitialPrice('1000.00')->setActualPrice('900.00')->setStock(1);
    }

    private function product(): Product
    {
        return (new Product())->setInitialPrice('1200.00')->setActualPrice('1100.00')->setStock(4);
    }
}
