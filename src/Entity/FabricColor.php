<?php

namespace App\Entity;

use App\Repository\FabricColorRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: FabricColorRepository::class)]
// Un même nom de coloris ne peut apparaître qu'une fois dans un tissu donné
#[ORM\UniqueConstraint(name: 'uniq_fabric_colorname', columns: ['fabric_id', 'name'])]
#[Vich\Uploadable]
class FabricColor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Le tissu auquel ce coloris appartient
    #[ORM\ManyToOne(inversedBy: 'fabricColors')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Fabric $fabric = null;

    // Le coloris porte désormais SON nom et SA couleur
    #[ORM\Column(length: 120)]
    private ?string $name = null;

    // Repli affiché tant qu'il n'y a pas de macro
    #[ORM\Column(length: 7)]
    private ?string $hex = null;

    // Le nom du fichier macro (nullable : le repli sur la pastille prend le relais)
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    // Le FICHIER uploadé (temporaire, PAS stocké en base)
    #[Vich\UploadableField(mapping: 'fabric_color', fileNameProperty: 'image')]
    private ?File $imageFile = null;

    // Doit changer quand on remplace le fichier, sinon Doctrine ne "voit" pas la modif
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // Ordre d'affichage dans le nuancier — commence à 1, jamais 0 ni négatif
    #[ORM\Column]
    private ?int $position = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFabric(): ?Fabric
    {
        return $this->fabric;
    }

    public function setFabric(?Fabric $fabric): static
    {
        $this->fabric = $fabric;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getHex(): ?string
    {
        return $this->hex;
    }

    public function setHex(string $hex): static
    {
        $this->hex = $hex;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;
        // Force Doctrine à détecter la modification : $imageFile n'étant pas une colonne,
        // sans ça le remplacement d'une macro passerait inaperçu.
        if ($imageFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    // Représentation texte : "Chenille — Moutarde"
    public function __toString(): string
    {
        return $this->fabric?->getName() . ' — ' . $this->name;
    }
}