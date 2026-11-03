<?php

namespace App\Service;

use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\DiscountType;

// Tous les montants d'un devis, à partir des prix TTC saisis. Calcul en chaînes avec bcmath, jamais en float :
// 0,1 + 0,2 ne fait pas exactement 0,3 en float, et un centime d'écart sur une facture ne pardonne pas.
// Le même calcul existe en JavaScript (assets/admin.js) pour l'affichage en direct ; celui-ci fait foi.
final class QuoteCalculator
{
    /**
     * Dans l'ordre : la remise de chaque ligne, puis la remise globale sur le total des articles.
     * Livraison et montage ne sont jamais remisés. Le HT et la TVA se tirent du total TTC.
     * Les taux de TVA et d'acompte sont ceux enregistrés sur le devis.
     *
     * L'acompte et le solde ont aussi leur HT et leur TVA, pour la facture d'acompte.
     *
     * @return array{lines: array<int, array{discount: string, total: string}>, items: string, discount: string,
     *     delivery: string, assembly: string, ttc: string, ht: string, tva: string, deposit: string, depositHt: string,
     *     depositTva: string, balance: string, balanceHt: string, balanceTva: string}
     */
    public function totals(Quote $quote): array
    {
        $lines = $quote->getLines()->map($this->line(...))->toArray();   // mêmes clés que les lignes
        $items = array_reduce($lines, static fn (string $sum, array $line) => bcadd($sum, $line['total'], 2), '0.00');
        $discount = $this->reduction($items, $quote->getDiscountValue(), $quote->getDiscountType());

        // Retrait au showroom : les prix saisis pour la livraison et le montage ne comptent pas
        $delivery = $quote->isPickup() ? '0.00' : ($quote->getDeliveryFee() ?? '0.00');
        $assembly = $quote->isPickup() ? '0.00' : ($quote->getAssemblyFee() ?? '0.00');

        $ttc = bcadd(bcsub($items, $discount, 2), bcadd($delivery, $assembly, 2), 2);
        $ht = $this->excludingVat($ttc, $quote->getVatRate());
        $deposit = $this->percentOf($ttc, $quote->getDepositRate());
        $depositHt = $this->excludingVat($deposit, $quote->getVatRate());

        // TVA et solde par différence : HT + TVA retombe toujours sur le TTC, acompte + solde sur le total
        $tva = bcsub($ttc, $ht, 2);
        $depositTva = bcsub($deposit, $depositHt, 2);

        return [
            'lines'      => $lines,
            'items'      => $items,
            'discount'   => $discount,
            'delivery'   => $delivery,
            'assembly'   => $assembly,
            'ttc'        => $ttc,
            'ht'         => $ht,
            'tva'        => $tva,
            'deposit'    => $deposit,
            'depositHt'  => $depositHt,
            'depositTva' => $depositTva,
            'balance'    => bcsub($ttc, $deposit, 2),
            'balanceHt'  => bcsub($ht, $depositHt, 2),
            'balanceTva' => bcsub($tva, $depositTva, 2),
        ];
    }

    // Le HT d'un montant TTC : TTC ÷ 1,2 pour une TVA à 20 %, arrondi au centime
    public function excludingVat(string $ttc, string $vatRate): string
    {
        return $this->round(bcdiv(bcmul($ttc, '100', 10), bcadd('100', $vatRate, 10), 10));
    }

    /**
     * @return array{discount: string, total: string}
     */
    private function line(QuoteLine $line): array
    {
        $gross = bcmul($line->getUnitPrice() ?? '0', (string) ($line->getQuantity() ?? 0), 2);
        $discount = $this->reduction($gross, $line->getDiscountValue(), $line->getDiscountType());

        return ['discount' => $discount, 'total' => bcsub($gross, $discount, 2)];
    }

    // La remise en euros d'une remise saisie en % ou en € : jamais plus que le montant remisé
    private function reduction(string $base, ?string $value, DiscountType $type): string
    {
        $reduction = $type === DiscountType::Percent ? $this->percentOf($base, $value ?? '0') : ($value ?? '0.00');

        return bccomp($reduction, $base, 2) > 0 ? $base : $reduction;
    }

    private function percentOf(string $amount, string $rate): string
    {
        return $this->round(bcdiv(bcmul($amount, $rate, 10), '100', 10));
    }

    // Arrondi au centime le plus proche. bcmath tronque : on ajoute d'abord un demi-centime (montants positifs).
    private function round(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }
}
