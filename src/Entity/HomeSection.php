<?php

namespace App\Entity;

use App\Repository\HomeSectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Une section de produits de l'accueil (Nouveautés, Promotions…) : ses produits sont choisis et rangés dans l'admin.
#[ORM\Entity(repositoryClass: HomeSectionRepository::class)]
class HomeSection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 120, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le sous-titre ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $subtitle = null;

    // L'ordre sur l'accueil, réglé au glisser-déposer de la liste des sections
    #[ORM\Column]
    private ?int $position = null;

    #[ORM\Column]
    private ?bool $isVisible = true;

    /**
     * Les produits avec leur rang : une ManyToMany ne garderait pas l'ordre, d'où l'entité de liaison.
     *
     * @var Collection<int, HomeSectionProduct>
     */
    #[ORM\OneToMany(targetEntity: HomeSectionProduct::class, mappedBy: 'section', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): static
    {
        $this->subtitle = $subtitle;

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

    public function isVisible(): ?bool
    {
        return $this->isVisible;
    }

    public function setIsVisible(bool $isVisible): static
    {
        $this->isVisible = $isVisible;

        return $this;
    }

    /**
     * @return Collection<int, HomeSectionProduct>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * Les produits dans leur ordre : ce que lisent les cases à cocher du formulaire et l'accueil.
     *
     * @return list<Product>
     */
    public function getProducts(): array
    {
        return $this->items->map(static fn (HomeSectionProduct $item) => $item->getProduct())->getValues();
    }

    // Un produit coché dans le formulaire se range à la fin de la section
    public function addProduct(Product $product): static
    {
        $last = $this->items->last();
        $this->items->add(new HomeSectionProduct($this, $product, $last ? $last->getPosition() + 1 : 1));

        return $this;
    }

    // Un produit décoché quitte la section : orphanRemoval supprime sa ligne de liaison
    public function removeProduct(Product $product): static
    {
        foreach ($this->items as $item) {
            if ($item->getProduct() === $product) {
                $this->items->removeElement($item);
            }
        }

        return $this;
    }
}
