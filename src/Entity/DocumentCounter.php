<?php

namespace App\Entity;

use App\Repository\DocumentCounterRepository;
use Doctrine\ORM\Mapping as ORM;

// Le dernier numéro attribué par type de document et par mois : « BHD-2026-11 » → 3.
// Un compteur plutôt que le plus grand numéro existant : supprimer le dernier devis ne libère pas son numéro.
// Lu et écrit seulement par DocumentCounterRepository::next().
#[ORM\Entity(repositoryClass: DocumentCounterRepository::class)]
class DocumentCounter
{
    #[ORM\Id]
    #[ORM\Column(length: 11)]
    private string $prefix;

    #[ORM\Column]
    private int $lastNumber;
}
