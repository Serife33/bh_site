<?php

namespace App\Tests\Entity;

use App\Entity\Product;
use App\Entity\ProductVariant;
use PHPUnit\Framework\TestCase;

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

    private function product(): Product
    {
        return (new Product())->setInitialPrice('1200.00')->setActualPrice('1100.00')->setStock(4);
    }
}
