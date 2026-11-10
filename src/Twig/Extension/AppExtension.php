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
            new TwigFunction('band_announcements', [AppExtensionRuntime::class, 'getBandAnnouncements']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('prix', [$this, 'prix']),
            new TwigFilter('remise', [$this, 'remise']),
            new TwigFilter('date_longue', [$this, 'dateLongue']),
            new TwigFilter('ht', [AppExtensionRuntime::class, 'ht']),
        ];
    }

    // Le format des prix du site public : « 1 320 € ». Aucun prix du catalogue n'a de centimes.
    // Les devis comptent au centime : prix(2) donne « 1 590,00 € » (affichage seulement, les calculs restent en chaînes).
    // Espaces insécables : un prix ne se coupe jamais en fin de ligne.
    public function prix(string|int|float $montant, int $decimales = 0): string
    {
        return number_format((float) $montant, $decimales, ',', "\u{A0}") . "\u{A0}€";
    }

    // Une date en toutes lettres sur les documents : « 2 novembre 2026 »
    public function dateLongue(\DateTimeInterface $date): string
    {
        return (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE))->format($date);
    }

    // Remise d'un produit ou d'une variante en pourcentage arrondi : 0 sans remise, au moins 1 dès que le prix baisse
    public function remise(Product|ProductVariant $offre): int
    {
        $actuel = (float) $offre->getActualPrice();
        $initial = (float) $offre->getInitialPrice();

        return $actuel < $initial ? max(1, (int) round(100 - 100 * $actuel / $initial)) : 0;
    }
}
