<?php

namespace App\Tests\Service;

use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\DiscountType;
use App\Service\QuoteCalculator;
use PHPUnit\Framework\TestCase;

final class QuoteCalculatorTest extends TestCase
{
    // Le devis BHD-2026-11-001 de la maquette edition.html, au centime près
    public function testMaquetteExample(): void
    {
        $quote = $this->quote(
            $this->line('1590.00', 1, '10.00', DiscountType::Percent),   // Canapé d'angle LONDON
            $this->line('1155.00', 1, '75.00', DiscountType::Amount),    // Lit coffre PARIS
            $this->line('480.00'),                                       // Tapis sur mesure, ligne libre
        )->setDiscountValue('90.00')->setDiscountType(DiscountType::Amount)
            ->setDeliveryFee('120.00')->setAssemblyFee('60.00');

        $totals = (new QuoteCalculator())->totals($quote);

        self::assertSame([
            ['discount' => '159.00', 'rate' => '10.00', 'total' => '1431.00'],
            ['discount' => '75.00', 'rate' => '6.49', 'total' => '1080.00'],
            ['discount' => '0.00', 'rate' => '0.00', 'total' => '480.00'],
        ], $totals['lines']);
        self::assertSame('2991.00', $totals['items']);
        self::assertSame('90.00', $totals['discount']);
        self::assertSame('3081.00', $totals['ttc']);
        self::assertSame('2567.50', $totals['ht']);
        self::assertSame('513.50', $totals['tva']);
        self::assertSame('924.30', $totals['deposit']);
        self::assertSame('2156.70', $totals['balance']);
        // La facture d'acompte BHF-2026-11-001 de la maquette facture-pdf.html
        self::assertSame('770.25', $totals['depositHt']);
        self::assertSame('154.05', $totals['depositTva']);
        self::assertSame('1797.25', $totals['balanceHt']);
        self::assertSame('359.45', $totals['balanceTva']);
    }

    // Le client donne 1 000 € d'acompte au lieu des 30 % (924,30 €) : le solde et la facture d'acompte suivent
    public function testDepositAmountReplacesTheRate(): void
    {
        $quote = $this->quote($this->line('3081.00'));
        $calculator = new QuoteCalculator();

        $byRate = $calculator->totals($quote);
        self::assertSame('924.30', $byRate['deposit']);
        self::assertSame('30.00', $byRate['depositShare']);

        $totals = $calculator->totals($quote->setDepositAmount('1000.00'));
        self::assertSame('1000.00', $totals['deposit']);
        self::assertSame('32.46', $totals['depositShare']);
        self::assertSame('2081.00', $totals['balance']);
        self::assertSame('833.33', $totals['depositHt']);
        self::assertSame('166.67', $totals['depositTva']);
        self::assertSame('1734.17', $totals['balanceHt']);   // 2 567,50 - 833,33
        self::assertSame('346.83', $totals['balanceTva']);   // 513,50 - 166,67
    }

    public function testAmountDiscountAppliesToTheWholeLine(): void
    {
        $totals = (new QuoteCalculator())->totals($this->quote($this->line('100.00', 2, '30.00', DiscountType::Amount)));

        self::assertSame('170.00', $totals['lines'][0]['total']);   // 2 × 100 - 30, et non 2 × (100 - 30)
    }

    public function testGlobalDiscountComesAfterLineDiscountsAndSparesFees(): void
    {
        $quote = $this->quote($this->line('1000.00', 1, '10.00', DiscountType::Percent))
            ->setDiscountValue('10.00')->setDeliveryFee('100.00')->setAssemblyFee('50.00');

        $totals = (new QuoteCalculator())->totals($quote);

        self::assertSame('90.00', $totals['discount']);   // 10 % des 900 € remisés, pas des 1 000 €
        self::assertSame('960.00', $totals['ttc']);       // 900 - 90 + 100 + 50
    }

    // Le vendeur cède le canapé à 2 000 € au lieu de 2 400 € : la remise se calcule seule, par canapé
    public function testFinalPriceGivesTheDiscount(): void
    {
        $calculator = new QuoteCalculator();

        $one = $calculator->totals($this->quote($this->line('2400.00', 1, '2000.00', DiscountType::FinalPrice)))['lines'][0];
        self::assertSame(['discount' => '400.00', 'rate' => '16.67', 'total' => '2000.00'], $one);

        $two = $calculator->totals($this->quote($this->line('2400.00', 2, '2000.00', DiscountType::FinalPrice)))['lines'][0];
        self::assertSame(['discount' => '800.00', 'rate' => '16.67', 'total' => '4000.00'], $two);

        $same = $calculator->totals($this->quote($this->line('2400.00', 1, '2400.00', DiscountType::FinalPrice)))['lines'][0];
        self::assertSame(['discount' => '0.00', 'rate' => '0.00', 'total' => '2400.00'], $same);
    }

    public function testGlobalDiscountComesAfterAFinalPrice(): void
    {
        $quote = $this->quote($this->line('2400.00', 1, '2000.00', DiscountType::FinalPrice), $this->line('500.00'))
            ->setDiscountValue('10.00')->setDeliveryFee('100.00');

        $totals = (new QuoteCalculator())->totals($quote);

        self::assertSame('2500.00', $totals['items']);   // 2 000 + 500
        self::assertSame('250.00', $totals['discount']); // 10 % des articles déjà remisés
        self::assertSame('2350.00', $totals['ttc']);     // 2 500 - 250 + 100 de livraison
    }

    public function testPickupIgnoresDeliveryAndAssembly(): void
    {
        $quote = $this->quote($this->line('500.00'))->setDeliveryFee('120.00')->setAssemblyFee('60.00')->setPickup(true);

        self::assertSame('500.00', (new QuoteCalculator())->totals($quote)['ttc']);
    }

    public function testRoundsToTheNearestCent(): void
    {
        $totals = (new QuoteCalculator())->totals($this->quote($this->line('33.33', 3, '15.00', DiscountType::Percent)));

        self::assertSame('15.00', $totals['lines'][0]['discount']);   // 15 % de 99,99 = 14,9985
        self::assertSame('84.99', $totals['ttc']);
        self::assertSame('70.83', $totals['ht']);                    // 84,99 ÷ 1,2 = 70,825
        self::assertSame('14.16', $totals['tva']);
        self::assertSame('25.50', $totals['deposit']);               // 30 % de 84,99 = 25,497
        self::assertSame('59.49', $totals['balance']);
    }

    public function testDiscountNeverExceedsWhatItReduces(): void
    {
        $quote = $this->quote($this->line('150.00', 1, '200.00', DiscountType::Amount), $this->line('80.00'))
            ->setDiscountValue('500.00')->setDiscountType(DiscountType::Amount)->setDeliveryFee('40.00');

        $totals = (new QuoteCalculator())->totals($quote);

        self::assertSame('0.00', $totals['lines'][0]['total']);
        self::assertSame('80.00', $totals['discount']);
        self::assertSame('40.00', $totals['ttc']);   // il reste la livraison, jamais remisée
    }

    private function quote(QuoteLine ...$lines): Quote
    {
        $quote = new Quote();
        foreach ($lines as $line) {
            $quote->addLine($line);
        }

        return $quote;
    }

    private function line(string $unitPrice, int $quantity = 1, string $discount = '0.00', DiscountType $type = DiscountType::Percent): QuoteLine
    {
        return (new QuoteLine())->setLabel('Ligne test')->setUnitPrice($unitPrice)->setQuantity($quantity)
            ->setDiscountValue($discount)->setDiscountType($type);
    }
}
