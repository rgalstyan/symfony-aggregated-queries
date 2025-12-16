<?php

declare(strict_types=1);

namespace Rgalstyan\SymfonyAggregatedQueries\Tests\Fixtures\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'partners')]
class Partner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 32)]
    private string $status = 'active';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Profile::class, inversedBy: 'partners')]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: true)]
    private ?Profile $profile = null;

    #[ORM\ManyToOne(targetEntity: Country::class, inversedBy: 'partners')]
    #[ORM\JoinColumn(name: 'country_id', referencedColumnName: 'id', nullable: true)]
    private ?Country $country = null;

    /**
     * @var Collection<int, Promocode>
     */
    #[ORM\OneToMany(mappedBy: 'partner', targetEntity: Promocode::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $promocodes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->promocodes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): void
    {
        $this->profile = $profile;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): void
    {
        $this->country = $country;
    }

    /**
     * @return Collection<int, Promocode>
     */
    public function getPromocodes(): Collection
    {
        return $this->promocodes;
    }

    public function addPromocode(Promocode $promocode): void
    {
        if ($this->promocodes->contains($promocode)) {
            return;
        }

        $this->promocodes->add($promocode);
        $promocode->setPartner($this);
    }
}

