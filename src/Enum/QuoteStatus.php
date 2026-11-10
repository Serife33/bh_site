<?php

namespace App\Enum;

// La vie d'un devis : brouillon, envoyé au client, puis accepté (signé), refusé ou expiré
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Expired = 'expired';

    // Le libellé de la liste, des filtres et du formulaire
    public function label(): string
    {
        return match ($this) {
            self::Draft    => 'Brouillon',
            self::Sent     => 'Envoyé',
            self::Accepted => 'Accepté',
            self::Refused  => 'Refusé',
            self::Expired  => 'Expiré',
        };
    }
}
