<?php

namespace App\Entity;

use App\Enum\QuoteStatus;
use App\Repository\QuoteRepository;
use App\Validator\DepositWithinTotal;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Un devis du showroom. Les montants sont saisis TTC ; QuoteCalculator en tire le HT, la TVA, l'acompte et le solde.
#[ORM\Entity(repositoryClass: QuoteRepository::class)]
#[DepositWithinTotal]
class Quote
{
    use DiscountTrait;   // la remise globale, sur le total des articles

    // Préfixe des numéros : BHD-2026-11-001
    public const NUMBER_PREFIX = 'BHD';
    public const VALIDITY_DAYS = 30;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Attribué au premier enregistrement (DocumentCounterRepository), il ne change plus
    #[ORM\Column(length: 20, unique: true)]
    private ?string $number = null;

    #[ORM\Column(enumType: QuoteStatus::class)]
    private QuoteStatus $status = QuoteStatus::Draft;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choisissez le client.')]
    private ?Customer $customer = null;

    // Le vendeur, « Votre conseiller » sur le devis : le compte connecté qui le crée
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choisissez le vendeur.')]
    private ?AdminUser $seller = null;

    /**
     * @var Collection<int, QuoteLine>
     */
    #[ORM\OneToMany(targetEntity: QuoteLine::class, mappedBy: 'quote', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]   // l'ordre de saisie : une ligne s'ajoute toujours à la fin
    #[Assert\Valid]
    #[Assert\Count(min: 1, max: 50, minMessage: 'Ajoutez au moins une ligne.', maxMessage: '{{ limit }} lignes au plus.')]
    private Collection $lines;

    // Livraison et montage sont des options : sans livraison, le client retire au showroom.
    // Le montage se coche indépendamment de la livraison.
    #[ORM\Column]
    private bool $delivery = false;

    #[ORM\Column]
    private bool $assembly = false;

    // Leurs prix, comptés seulement si l'option est cochée ; 0 : offert. Jamais remisés, compris dans l'acompte.
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    #[Assert\LessThanOrEqual(value: 5000, message: 'Prix invraisemblable : {{ compared_value }} € maximum.')]
    private ?string $deliveryFee = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    #[Assert\LessThanOrEqual(value: 5000, message: 'Prix invraisemblable : {{ compared_value }} € maximum.')]
    private ?string $assemblyFee = '0.00';

    // Les taux en % du jour de la création, gardés avec le devis : changer ces valeurs par défaut
    // pour les nouveaux devis ne modifie ni un devis signé ni sa facture
    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2)]
    private string $vatRate = '20.00';

    // L'acompte, en % du total TTC, livraison et montage compris
    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2)]
    private string $depositRate = '30.00';

    // L'acompte TTC demandé, quand le client en donne un autre que celui du taux (1 000 € au lieu de 984,30 €).
    // Vide : le taux s'applique.
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    #[Assert\Positive(message: "L'acompte doit être supérieur à 0, ou laissez le champ vide pour le pourcentage habituel.")]
    private ?string $depositAmount = null;

    // Le total TTC recalculé à chaque enregistrement, pour la liste des devis.
    // 12 chiffres : 50 lignes au prix et à la quantité maximum tiennent encore.
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $total = '0.00';

    // Ex. « 6 à 10 semaines après réception de l'acompte ». Obligatoire : la loi demande un délai de livraison
    // quand la vente n'est pas exécutée tout de suite, et les CGV le promettent sur le récapitulatif de commande.
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'Indiquez le délai de livraison, ou « Disponible immédiatement ».')]
    #[Assert\Length(max: 255, maxMessage: 'Le délai ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $leadTime = null;

    // Visibles seulement dans l'administration, jamais sur le devis imprimé
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères.')]
    private ?string $notes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Indiquez la date de validité.')]
    private ?\DateTimeImmutable $validUntil;

    // Date de la signature : le passage au statut Accepté
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->validUntil = $this->createdAt->modify('+' . self::VALIDITY_DAYS . ' days')->setTime(0, 0);
    }

    // Un devis signé ne se modifie plus
    public function isLocked(): bool
    {
        return $this->status === QuoteStatus::Accepted;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): QuoteStatus
    {
        return $this->status;
    }

    public function setStatus(QuoteStatus $status): static
    {
        if ($status === QuoteStatus::Accepted && $this->acceptedAt === null) {
            $this->acceptedAt = new \DateTimeImmutable();
        }
        $this->status = $status;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getSeller(): ?AdminUser
    {
        return $this->seller;
    }

    public function setSeller(?AdminUser $seller): static
    {
        $this->seller = $seller;

        return $this;
    }

    /**
     * @return Collection<int, QuoteLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(QuoteLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setQuote($this);
        }

        return $this;
    }

    public function removeLine(QuoteLine $line): static
    {
        if ($this->lines->removeElement($line) && $line->getQuote() === $this) {
            $line->setQuote(null);
        }

        return $this;
    }

    public function isDelivery(): bool
    {
        return $this->delivery;
    }

    public function setDelivery(bool $delivery): static
    {
        $this->delivery = $delivery;

        return $this;
    }

    public function isAssembly(): bool
    {
        return $this->assembly;
    }

    public function setAssembly(bool $assembly): static
    {
        $this->assembly = $assembly;

        return $this;
    }

    public function getDeliveryFee(): ?string
    {
        return $this->deliveryFee;
    }

    public function setDeliveryFee(?string $deliveryFee): static
    {
        $this->deliveryFee = $deliveryFee;

        return $this;
    }

    public function getAssemblyFee(): ?string
    {
        return $this->assemblyFee;
    }

    public function setAssemblyFee(?string $assemblyFee): static
    {
        $this->assemblyFee = $assemblyFee;

        return $this;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function getDepositRate(): string
    {
        return $this->depositRate;
    }

    public function getDepositAmount(): ?string
    {
        return $this->depositAmount;
    }

    public function setDepositAmount(?string $depositAmount): static
    {
        $this->depositAmount = $depositAmount;

        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getLeadTime(): ?string
    {
        return $this->leadTime;
    }

    public function setLeadTime(?string $leadTime): static
    {
        $this->leadTime = $leadTime;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): static
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }
}
