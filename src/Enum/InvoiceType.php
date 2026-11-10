<?php

namespace App\Enum;

// Une commande donne deux factures : l'acompte à la signature, puis le solde qui déduit cet acompte
enum InvoiceType: string
{
    case Deposit = 'deposit';
    case Balance = 'balance';

    // Le titre imprimé sur la facture
    public function label(): string
    {
        return match ($this) {
            self::Deposit => "Facture d'acompte",
            self::Balance => 'Facture de solde',
        };
    }
}
