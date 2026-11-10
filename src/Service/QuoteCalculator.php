<?php

namespace App\Service;

use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\DiscountType;
use App\Enum\ProductModular;

// Tous les montants d'un devis, à partir des prix TTC saisis. Calcul en chaînes avec bcmath, jamais en float :
// 0,1 + 0,2 ne fait pas exactement 0,3 en float, et un centime d'écart sur une facture ne pardonne pas.
// Le même calcul existe en JavaScript (assets/devis.js) pour l'affichage en direct ; celui-ci fait foi.
final class QuoteCalculator
{
    /**
     * Dans l'ordre : la remise de chaque ligne, puis la remise globale sur le total des articles.
     * Livraison et montage ne sont jamais remisés. Le HT et la TVA se tirent du total TTC.
     * Les taux de TVA et d'acompte sont ceux enregistrés sur le devis.
     *
     * L'acompte est le montant saisi sur le devis, sinon son taux appliqué au total TTC ; sa part du total
     * l'accompagne (« 32,46 % »). L'acompte et le solde ont aussi leur HT et leur TVA, pour la facture d'acompte.
     * Les compositions (canapés composés module par module) ont leur sous-total TTC et HT, pour les documents.
     *
     * @return array{lines: array<int, array{discount: string, rate: string, total: string}>,
     *     compositions: list<array{family: string, first: int, last: int, total: string, ht: string}>, items: string, discount: string,
     *     delivery: string, assembly: string, ttc: string, ht: string, tva: string, deposit: string, depositShare: string,
     *     depositHt: string, depositTva: string, balance: string, balanceHt: string, balanceTva: string}
     */
    public function totals(Quote $quote): array
    {
        $lines = $quote->getLines()->map($this->line(...))->toArray();   // mêmes clés que les lignes
        $items = array_reduce($lines, static fn (string $sum, array $line) => bcadd($sum, $line['total'], 2), '0.00');
        $discount = $this->reduction($items, $quote->getDiscountValue(), $quote->getDiscountType());

        // Livraison et montage comptent seulement s'ils sont cochés
        $delivery = $quote->isDelivery() ? ($quote->getDeliveryFee() ?? '0.00') : '0.00';
        $assembly = $quote->isAssembly() ? ($quote->getAssemblyFee() ?? '0.00') : '0.00';

        $ttc = bcadd(bcsub($items, $discount, 2), bcadd($delivery, $assembly, 2), 2);
        $ht = $this->excludingVat($ttc, $quote->getVatRate());
        $deposit = $quote->getDepositAmount() ?? $this->percentOf($ttc, $quote->getDepositRate());
        $depositHt = $this->excludingVat($deposit, $quote->getVatRate());

        // TVA et solde par différence : HT + TVA retombe toujours sur le TTC, acompte + solde sur le total
        $tva = bcsub($ttc, $ht, 2);
        $depositTva = bcsub($deposit, $depositHt, 2);

        // La part du total qu'est l'acompte : son taux, ou celle du montant saisi
        $depositShare = $quote->getDepositAmount() === null || bccomp($ttc, '0', 2) <= 0
            ? $quote->getDepositRate()
            : $this->round(bcdiv(bcmul($deposit, '100', 10), $ttc, 10));

        return [
            'lines'        => $lines,
            'compositions' => $this->compositions($quote, $lines),
            'items'        => $items,
            'discount'     => $discount,
            'delivery'     => $delivery,
            'assembly'     => $assembly,
            'ttc'          => $ttc,
            'ht'           => $ht,
            'tva'          => $tva,
            'deposit'      => $deposit,
            'depositShare' => $depositShare,
            'depositHt'    => $depositHt,
            'depositTva'   => $depositTva,
            'balance'      => bcsub($ttc, $deposit, 2),
            'balanceHt'    => bcsub($ht, $depositHt, 2),
            'balanceTva'   => bcsub($tva, $depositTva, 2),
        ];
    }

    /**
     * Les modules d'une même gamme qui se suivent, deux au moins : un canapé composé dans le devis, regroupé sur les
     * documents sous son titre, avec son sous-total. Déduit des produits des lignes et de leur ordre. Le HT est la somme des
     * HT des lignes, tels que la facture d'acompte les imprime : la colonne s'additionne au centime.
     *
     * @return list<array{family: string, first: int, last: int, total: string, ht: string}>
     */
    private function compositions(Quote $quote, array $lines): array
    {
        $groups = [];
        $previous = null;
        foreach ($quote->getLines() as $key => $line) {
            $product = $line->getProduct();
            $family = $product?->getIsModular() === ProductModular::Module ? $product->getFamily() : null;
            if ($family !== null && $family !== $previous) {
                $groups[] = ['family' => $family->getName(), 'first' => $key, 'last' => $key, 'total' => '0.00', 'ht' => '0.00'];
            }
            if ($family !== null) {
                $last = count($groups) - 1;
                $groups[$last]['last'] = $key;
                $groups[$last]['total'] = bcadd($groups[$last]['total'], $lines[$key]['total'], 2);
                $groups[$last]['ht'] = bcadd($groups[$last]['ht'], $this->excludingVat($lines[$key]['total'], $quote->getVatRate()), 2);
            }
            $previous = $family;
        }

        return array_values(array_filter($groups, static fn (array $group) => $group['first'] !== $group['last']));
    }

    // Le HT d'un montant TTC : TTC ÷ 1,2 pour une TVA à 20 %, arrondi au centime
    public function excludingVat(string $ttc, string $vatRate): string
    {
        return $this->round(bcdiv(bcmul($ttc, '100', 10), bcadd('100', $vatRate, 10), 10));
    }

    /**
     * La remise en euros et en % du montant de la ligne (« soit -16,67 % » d'un prix final), et le total.
     *
     * @return array{discount: string, rate: string, total: string}
     */
    private function line(QuoteLine $line): array
    {
        $quantity = $line->getQuantity() ?? 0;
        $gross = bcmul($line->getUnitPrice() ?? '0', (string) $quantity, 2);
        $discount = $this->reduction($gross, $line->getDiscountValue(), $line->getDiscountType(), $quantity);

        return [
            'discount' => $discount,
            'rate'     => bccomp($gross, '0', 2) > 0 ? $this->round(bcdiv(bcmul($discount, '100', 10), $gross, 10)) : '0.00',
            'total'    => bcsub($gross, $discount, 2),
        ];
    }

    // La remise en euros d'une remise saisie en %, en € ou en prix final unitaire : jamais plus que le montant remisé
    private function reduction(string $base, ?string $value, DiscountType $type, int $quantity = 1): string
    {
        $value ??= '0';
        $reduction = match ($type) {
            DiscountType::Percent    => $this->percentOf($base, $value),
            DiscountType::Amount     => $value,
            DiscountType::FinalPrice => bcsub($base, bcmul($value, (string) $quantity, 2), 2),   // (prix unitaire - prix final) × quantité
        };

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
