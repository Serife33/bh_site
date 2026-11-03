<?php

namespace App\Entity;

use App\Repository\AnnouncementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Un message du bandeau défilant. Avec des dates, c'est une campagne (« Black Friday » du 27 au 30/11).
#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
class Announcement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    #[Assert\Length(max: 120, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $text = null;

    // Un chemin du site (« /offres ») ou une adresse https : tout autre schéma (javascript:, data:, http:) est refusé.
    // « // » ou « /\ » en tête mèneraient vers un autre site : refusés aussi.
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le lien ne peut pas dépasser {{ limit }} caractères.')]
    #[Assert\Regex(
        pattern: '#^(/(?![/\\\\])|https://\w)\S*\z#',
        message: 'Le lien doit commencer par « / » (une page du site, ex. /offres) ou par « https:// ».',
    )]
    private ?string $link = null;

    // Mis en avant (style band-promo) et placé avant les autres messages
    #[ORM\Column]
    private ?bool $isPromo = false;

    // Sans date de début ni de fin, le message est permanent. Les deux jours sont inclus.
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'startDate',
        message: 'La date de fin ne peut pas précéder la date de début.',
    )]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    #[Assert\Positive(message: "L'ordre d'affichage doit être un nombre positif.")]
    private ?int $position = 1;

    #[ORM\Column]
    private ?bool $isVisible = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function isPromo(): ?bool
    {
        return $this->isPromo;
    }

    public function setIsPromo(bool $isPromo): static
    {
        $this->isPromo = $isPromo;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

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

    // Le jour du bandeau : celui de Paris, quel que soit le fuseau du serveur (le projet n'en fixe aucun)
    public static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
    }

    // L'état affiché dans la liste de l'admin, selon la même règle que AnnouncementRepository::findVisibleOn.
    // Les dates se comparent au format Y-m-d : seul le jour compte, pas l'heure ni le fuseau.
    public function getStatus(\DateTimeImmutable $today): string
    {
        $day = $today->format('Y-m-d');

        return match (true) {
            !$this->isVisible => 'Masqué',
            $this->startDate !== null && $this->startDate->format('Y-m-d') > $day => 'Programmé',
            $this->endDate !== null && $this->endDate->format('Y-m-d') < $day => 'Terminé',
            default => 'En ligne',
        };
    }

    public function __toString(): string
    {
        return $this->text ?? '';
    }
}
