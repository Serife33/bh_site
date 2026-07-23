<?php

namespace App\Entity;

use App\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;


#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[Vich\Uploadable] 
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    
    // Le FICHIER uploadé (temporaire, PAS stocké en base). Vich range le fichier sur le disque et écrit son nom dans $url.
    #[Vich\UploadableField(mapping: 'product_media', fileNameProperty: 'url')]
    private ?File $imageFile = null;

    // Doit changer quand on remplace le fichier, sinon Doctrine ne "voit" pas la modif.
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(length: 10)]
    private ?string $type = 'photo';

    #[ORM\Column]
    private ?bool $isMain = false;

    #[ORM\Column]
    private ?int $position = 0;

    #[ORM\ManyToOne(inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isMain(): ?bool
    {
        return $this->isMain;
    }

    public function setIsMain(bool $isMain): static
    {
        $this->isMain = $isMain;

        return $this;
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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;              
    }

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile; 
        // Force Doctrine à détecter la modification : $imageFile n'étant pas une colonne, sans ça le remplacement d'une photo passerait inaperçu.       
        if ($imageFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();  
        }
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;             
    }
}
