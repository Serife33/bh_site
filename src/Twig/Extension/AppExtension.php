<?php

namespace App\Twig\Extension;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Twig\Runtime\AppExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_categories', [AppExtensionRuntime::class, 'getNavCategories']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('prix', [$this, 'prix']),
            new TwigFilter('remise', [$this, 'remise']),
        ];
    }

    // Le format des prix du site public : « 1 320 € ». Aucun prix en base n'a de centimes.
    // Espaces insécables : un prix ne se coupe jamais en fin de ligne.
    public function prix(string|int|float $montant): string
    {
        return number_format((float) $montant, 0, ',', "\u{A0}") . "\u{A0}€";
    }

    // Remise d'un produit ou d'une variante en pourcentage arrondi : 0 sans remise, au moins 1 dès que le prix baisse
    public function remise(Product|ProductVariant $offre): int
    {
        $actuel = (float) $offre->getActualPrice();
        $initial = (float) $offre->getInitialPrice();

        return $actuel < $initial ? max(1, (int) round(100 - 100 * $actuel / $initial)) : 0;
    }
}
