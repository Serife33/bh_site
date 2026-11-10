<?php

namespace App\Entity;

use App\Enum\InvoiceType;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\Mapping as ORM;

// La facture d'acompte ou de solde d'un devis accepté (les avoirs viendront ensuite).
// Brouillon tant qu'elle n'a pas de numéro : elle se supprime. Émise, elle ne se modifie ni ne se supprime plus,
// et ses numéros se suivent sans trou : une erreur se corrigera par un avoir.
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
{
    // Préfixe des numéros : BHF-2026-11-001
    public const NUMBER_PREFIX = 'BHF';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Un devis qui a une facture ne se supprime pas : la base le refuse
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Quote $quote;

    #[ORM\Column(enumType: InvoiceType::class, options: ['default' => 'deposit'])]
    private InvoiceType $type;

    // Attribué à l'émission (DocumentCounterRepository), dans une seule suite pour l'acompte et le solde
    #[ORM\Column(length: 20, unique: true, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $issuedAt = null;

    // Le client tel qu'il était à l'émission : modifier sa fiche ensuite ne change pas une facture émise
    #[ORM\Column(length: 255)]
    private string $customerName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customerAddress;

    public function __construct(Quote $quote, InvoiceType $type = InvoiceType::Deposit)
    {
        $this->quote = $quote;
        $this->type = $type;
        $this->copyCustomer();
    }

    public function getType(): InvoiceType
    {
        return $this->type;
    }

    public function issue(string $number, \DateTimeImmutable $issuedAt): void
    {
        $this->number = $number;
        $this->issuedAt = $issuedAt;
        $this->copyCustomer();
    }

    public function isIssued(): bool
    {
        return $this->number !== null;
    }

    private function copyCustomer(): void
    {
        $customer = $this->quote->getCustomer();
        $this->customerName = (string) $customer;
        $this->customerAddress = $customer->getAddress();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuote(): Quote
    {
        return $this->quote;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getCustomerAddress(): ?string
    {
        return $this->customerAddress;
    }
}
