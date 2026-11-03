<?php

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Le fichier client du showroom : un client peut avoir plusieurs devis
#[ORM\Entity(repositoryClass: CustomerRepository::class)]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $lastName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $firstName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^\+?[0-9 .()-]{6,20}$/', message: 'Ce numéro de téléphone ne semble pas valide.')]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'Cette adresse e-mail ne semble pas valide.')]
    #[Assert\Length(max: 180, maxMessage: "L'e-mail ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $email = null;

    // L'adresse de livraison, en une ligne : numéro et rue, code postal, ville
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: "L'adresse ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $address = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    // « Marie Dupont », sur les documents et dans les listes
    public function __toString(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
