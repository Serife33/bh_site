<?php

namespace App\Entity;

use App\Enum\ProductModular;
use App\Enum\ProductSide;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]   // dit à Doctrine : "cette entité a des callbacks, va les chercher"
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private ?string $name = null;

    #[ORM\Column(length: 180)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $dimension = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $initialPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $actualPrice = null;

    #[ORM\Column]
    private ?int $stock = null;

    #[ORM\Column]
    private ?bool $isCustomMade = null;

    #[ORM\Column(enumType: ProductModular::class)]
    private ?ProductModular $isModular = null;

    #[ORM\Column(enumType: ProductSide::class)]
    private ?ProductSide $sideLr = null;

    #[ORM\Column(nullable: true)]
    private ?int $leadMinWeeks = null;

    #[ORM\Column(nullable: true)]
    private ?int $leadMaxWeeks = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $metaTitle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column]
    private ?int $position = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?Family $family = null;

    /**
     * @var Collection<int, SubCategory>
     */
    #[ORM\ManyToMany(targetEntity: SubCategory::class, inversedBy: 'products')]
    private Collection $subCategories;

    /**
     * @var Collection<int, Fabric>
     */
    #[ORM\ManyToMany(targetEntity: Fabric::class, inversedBy: 'products')]
    private Collection $fabrics;

    /**
     * @var Collection<int, Color>
     */
    #[ORM\ManyToMany(targetEntity: Color::class, inversedBy: 'products')]
    private Collection $colors;

    /**
     * @var Collection<int, self>
     */
    #[ORM\ManyToMany(targetEntity: self::class)]
    private Collection $modules;

    /**
     * @var Collection<int, Media>
     */
    #[ORM\OneToMany(targetEntity: Media::class, mappedBy: 'product', orphanRemoval: true)]
    private Collection $media;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->subCategories = new ArrayCollection();
        $this->fabrics = new ArrayCollection();
        $this->colors = new ArrayCollection();
        $this->modules = new ArrayCollection();
        $this->media = new ArrayCollection();
    }


    
    // Appelée automatiquement par Doctrine juste avant le premier INSERT.
    #[ORM\PrePersist]
    public function initTimestamps(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Appelée automatiquement par Doctrine juste avant chaque UPDATE.
    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Génère le slug depuis le nom, juste avant le premier INSERT.
    // Volontairement PAS sur PreUpdate : une URL ne doit pas changer quand on renomme un produit (les liens existants et le référencement casseraient).
    #[ORM\PrePersist]
    public function generateSlug() : void
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDimension(): ?string
    {
        return $this->dimension;
    }

    public function setDimension(?string $dimension): static
    {
        $this->dimension = $dimension;

        return $this;
    }

    public function getInitialPrice(): ?string
    {
        return $this->initialPrice;
    }

    public function setInitialPrice(string $initialPrice): static
    {
        $this->initialPrice = $initialPrice;

        return $this;
    }

    public function getActualPrice(): ?string
    {
        return $this->actualPrice;
    }

    public function setActualPrice(string $actualPrice): static
    {
        $this->actualPrice = $actualPrice;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function isCustomMade(): ?bool
    {
        return $this->isCustomMade;
    }

    public function setIsCustomMade(bool $isCustomMade): static
    {
        $this->isCustomMade = $isCustomMade;

        return $this;
    }

    public function getIsModular(): ?ProductModular
    {
        return $this->isModular;
    }

    public function setIsModular(ProductModular $isModular): static
    {
        $this->isModular = $isModular;

        return $this;
    }

    public function getSideLr(): ?ProductSide
    {
        return $this->sideLr;
    }

    public function setSideLr(ProductSide $sideLr): static
    {
        $this->sideLr = $sideLr;

        return $this;
    }

    public function getLeadMinWeeks(): ?int
    {
        return $this->leadMinWeeks;
    }

    public function setLeadMinWeeks(?int $leadMinWeeks): static
    {
        $this->leadMinWeeks = $leadMinWeeks;

        return $this;
    }

    public function getLeadMaxWeeks(): ?int
    {
        return $this->leadMaxWeeks;
    }

    public function setLeadMaxWeeks(?int $leadMaxWeeks): static
    {
        $this->leadMaxWeeks = $leadMaxWeeks;

        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $this->metaTitle = $metaTitle;

        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;

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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getFamily(): ?Family
    {
        return $this->family;
    }

    public function setFamily(?Family $family): static
    {
        $this->family = $family;

        return $this;
    }

    /**
     * @return Collection<int, SubCategory>
     */
    public function getSubCategories(): Collection
    {
        return $this->subCategories;
    }

    public function addSubCategory(SubCategory $subCategory): static
    {
        if (!$this->subCategories->contains($subCategory)) {
            $this->subCategories->add($subCategory);
        }

        return $this;
    }

    public function removeSubCategory(SubCategory $subCategory): static
    {
        $this->subCategories->removeElement($subCategory);

        return $this;
    }

    /**
     * @return Collection<int, Fabric>
     */
    public function getFabrics(): Collection
    {
        return $this->fabrics;
    }

    public function addFabric(Fabric $fabric): static
    {
        if (!$this->fabrics->contains($fabric)) {
            $this->fabrics->add($fabric);
        }

        return $this;
    }

    public function removeFabric(Fabric $fabric): static
    {
        $this->fabrics->removeElement($fabric);

        return $this;
    }

    /**
     * @return Collection<int, Color>
     */
    public function getColors(): Collection
    {
        return $this->colors;
    }

    public function addColor(Color $color): static
    {
        if (!$this->colors->contains($color)) {
            $this->colors->add($color);
        }

        return $this;
    }

    public function removeColor(Color $color): static
    {
        $this->colors->removeElement($color);

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getModules(): Collection
    {
        return $this->modules;
    }

    public function addModule(self $module): static
    {
        if (!$this->modules->contains($module)) {
            $this->modules->add($module);
        }

        return $this;
    }

    public function removeModule(self $module): static
    {
        $this->modules->removeElement($module);

        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedium(Media $medium): static
    {
        if (!$this->media->contains($medium)) {
            $this->media->add($medium);
            $medium->setProduct($this);
        }

        return $this;
    }

    public function removeMedium(Media $medium): static
    {
        if ($this->media->removeElement($medium)) {
            // set the owning side to null (unless already changed)
            if ($medium->getProduct() === $this) {
                $medium->setProduct(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

}
