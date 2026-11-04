<?php

namespace App\Entity;

use App\Repository\HomeSectionProductRepository;
use Doctrine\ORM\Mapping as ORM;

// Un produit dans une section de l'accueil, à son rang. Créé par HomeSection::addProduct().
#[ORM\Entity(repositoryClass: HomeSectionProductRepository::class)]
class HomeSectionProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Supprimer la section ou le produit supprime la ligne : Doctrine s'en charge, et la base aussi
    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private HomeSection $section;

    // EAGER : la page d'une section affiche ses produits sans une requête par ligne
    #[ORM\ManyToOne(fetch: 'EAGER')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column]
    private int $position;

    public function __construct(HomeSection $section, Product $product, int $position)
    {
        $this->section = $section;
        $this->product = $product;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
