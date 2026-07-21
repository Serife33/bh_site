<?php

namespace App\Entity;

use App\Repository\SubCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: SubCategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SubCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private ?string $name = null;

    #[ORM\Column(length: 140)]
    private ?string $slug = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'subCategories')]
    private Collection $products;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }




    // Génère le slug depuis le nom, à la création seulement (URLs stables = SEO).
    #[ORM\PrePersist]
    public function generateSlug(): void
    {
        $this->slug = (new AsciiSlugger())->slug($this->name)->lower();
    }




    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->addSubCategory($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            $product->removeSubCategory($this);
        }

        return $this;
    }

    // Affiche le nom quand l'objet est converti en texte (listes déroulantes du form Product)
    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
