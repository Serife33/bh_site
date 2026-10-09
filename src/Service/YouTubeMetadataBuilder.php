<?php

namespace App\Service;

use App\Entity\Product;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Compose le titre et la description envoyés à YouTube avec une vidéo de produit.
 *
 * Ces deux textes sont indexés par la recherche YouTube et par Google : une vidéo
 * sans description n'est trouvable par personne. Ils restent modifiables dans le
 * formulaire avant l'envoi.
 */
class YouTubeMetadataBuilder
{
    // Limites imposées par l'API YouTube.
    private const TITLE_MAX = 100;
    private const DESCRIPTION_MAX = 5000;

    // Le pied de description, identique sous chaque vidéo de la chaîne.
    private const STORE_NAME = 'Brillance Home';
    private const STORE_STREET = '12 bis rue Suffren';
    private const STORE_CITY = '33300 Bordeaux';
    private const STORE_PHONE = '07 81 07 10 71';

    private readonly string $siteUrl;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(SITE_URL)%')] string $siteUrl,
    ) {
        $this->siteUrl = rtrim($siteUrl, '/');
    }

    public function buildTitle(Product $product): string
    {
        // « Canapé Oslo » ne sort sur aucune recherche locale ; « Canapé Oslo —
        // Brillance Home Bordeaux » peut sortir sur « canapé Bordeaux ».
        $suffix = ' — ' . self::STORE_NAME . ' Bordeaux';

        return $this->fit((string) $product->getName(), self::TITLE_MAX - mb_strlen($suffix)) . $suffix;
    }

    public function buildDescription(Product $product): string
    {
        $footer = sprintf(
            "%s\n%s, %s\n%s\n%s",
            self::STORE_NAME,
            self::STORE_STREET,
            self::STORE_CITY,
            self::STORE_PHONE,
            $this->siteUrl,
        );

        $tail = "\n\nVoir la fiche : " . $this->productUrl($product) . "\n\n" . $footer;

        // La description du produit passe en premier, tronquée pour que le lien et
        // le pied tiennent toujours dans la limite de YouTube.
        $intro = $this->fit(trim((string) $product->getDescription()), self::DESCRIPTION_MAX - mb_strlen($tail));

        return $intro === '' ? ltrim($tail) : $intro . $tail;
    }

    private function productUrl(Product $product): string
    {
        // Le chemin vient du routeur, le domaine de SITE_URL : en local, le routeur
        // produirait « localhost:8080 » et le lien partirait cassé sur YouTube.
        return $this->siteUrl . $this->urlGenerator->generate('front_product', ['slug' => $product->getSlug()]);
    }

    private function fit(string $text, int $max): string
    {
        return mb_strlen($text) > $max
            ? rtrim(mb_substr($text, 0, $max - 1)) . '…'
            : $text;
    }
}