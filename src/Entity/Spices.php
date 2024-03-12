<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpicesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @Vich\Uploadable
 */
#[ORM\Entity(repositoryClass: SpicesRepository::class)]
#[ORM\Table(name: 'spices')]
class Spices
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AromaticGroups::class, inversedBy: 'spices')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'id', name: 'aromaticGroups')]
    private ?AromaticGroups $aromaticGroups = null;

    #[ORM\ManyToOne(targetEntity: SpicyType::class, inversedBy: 'spices')]
    #[ORM\JoinColumn(referencedColumnName: 'id', name: 'spicy_type')]
    private ?SpicyType $spicyType = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cooking = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $informations = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $file = null;

    /**
     * @Vich\UploadableField(mapping="spice_images", fileNameProperty="file", size="imageSize")
     */
    private ?File $imageFile = null;

    #[ORM\Column(type: 'integer')]
    private ?int $imageSize = null;

    /** @var  Collection<int, AromaticCompound> */
    #[ORM\ManyToMany(targetEntity: AromaticCompound::class, inversedBy: 'spices')]
    private Collection $aromaticsCompounds;

    /** @var  Collection<int, AromaticCompound> */
    #[ORM\ManyToMany(targetEntity: AromaticCompound::class, inversedBy: 'secondary_spices')]
    #[ORM\JoinTable(name: 'secondary_spices_aromatic_compound')]
    private Collection $secondary_aromatics_compounds;

    /** @var  Collection<int, CookingTips> */
    #[ORM\OneToMany(mappedBy: 'spice', targetEntity: CookingTips::class, orphanRemoval: true)]
    private Collection $cookingTips;

    /** @var  Collection<int, PreparationTips> */
    #[ORM\OneToMany(mappedBy: 'spice', targetEntity: PreparationTips::class, orphanRemoval: true)]
    private Collection $preparationTips;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $benefits = null;

    public function __construct()
    {
        $this->aromaticsCompounds = new ArrayCollection();
        $this->secondary_aromatics_compounds = new ArrayCollection();
        $this->cookingTips = new ArrayCollection();
        $this->preparationTips = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAromaticGroups(): ?AromaticGroups
    {
        return $this->aromaticGroups;
    }

    public function setAromaticGroups(?AromaticGroups $aromaticGroups): self
    {
        $this->aromaticGroups = $aromaticGroups;

        return $this;
    }

    public function getSpicyType(): ?SpicyType
    {
        return $this->spicyType;
    }

    public function setSpicyType(?SpicyType $spicyType): self
    {
        $this->spicyType = $spicyType;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCooking(): ?string
    {
        return $this->cooking;
    }

    public function setCooking(?string $cooking): self
    {
        $this->cooking = $cooking;

        return $this;
    }

    public function getInformations(): ?string
    {
        return $this->informations;
    }

    public function setInformations(?string $informations): self
    {
        $this->informations = $informations;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?\DateTimeInterface $deleted_at): self
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): self
    {
        $this->file = $file;

        return $this;
    }

    /**
     * @param File|UploadedFile|null $imageFile
     */
    public function setImageFile(
        ?File $imageFile = null
    ): void {
        $this->imageFile = $imageFile;

        if ($imageFile instanceof File) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updated_at = new \DateTime(
                'now'
            );
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageSize(?int $imageSize): void
    {
        $this->imageSize = $imageSize;
    }

    public function getImageSize(): ?int
    {
        return $this->imageSize;
    }

    /**
     * @return Collection<int, AromaticCompound>
     */
    public function getAromaticsCompounds(): Collection
    {
        return $this->aromaticsCompounds;
    }

    public function addAromaticsCompounds(AromaticCompound $aromaticsCompounds): self
    {
        if (! $this->aromaticsCompounds->contains($aromaticsCompounds)) {
            $this->aromaticsCompounds[] = $aromaticsCompounds;
        }

        return $this;
    }

    public function removeAromaticsCompounds(AromaticCompound $aromaticsCompounds): self
    {
        $this->aromaticsCompounds->removeElement($aromaticsCompounds);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @return Collection<int, AromaticCompound>
     */
    public function getSecondaryAromaticsCompounds(): Collection
    {
        return $this->secondary_aromatics_compounds;
    }

    public function addSecondaryAromaticsCompound(AromaticCompound $secondaryAromaticsCompound): self
    {
        if (! $this->secondary_aromatics_compounds->contains($secondaryAromaticsCompound)) {
            $this->secondary_aromatics_compounds[] = $secondaryAromaticsCompound;
        }

        return $this;
    }

    public function removeSecondaryAromaticsCompound(AromaticCompound $secondaryAromaticsCompound): self
    {
        $this->secondary_aromatics_compounds->removeElement($secondaryAromaticsCompound);

        return $this;
    }

    /**
     * @return Collection<int, CookingTips>
     */
    public function getCookingTips(): Collection
    {
        return $this->cookingTips;
    }

    public function addCookingTip(CookingTips $cookingTip): static
    {
        if (! $this->cookingTips->contains($cookingTip)) {
            $this->cookingTips->add($cookingTip);
            $cookingTip->setSpice($this);
        }

        return $this;
    }

    public function removeCookingTip(CookingTips $cookingTip): static
    {
        // set the owning side to null (unless already changed)
        if ($this->cookingTips->removeElement($cookingTip) && $cookingTip->getSpice() === $this) {
            $cookingTip->setSpice(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, PreparationTips>
     */
    public function getPreparationTips(): Collection
    {
        return $this->preparationTips;
    }

    public function addPreparationTip(PreparationTips $preparationTip): static
    {
        if (! $this->preparationTips->contains($preparationTip)) {
            $this->preparationTips->add($preparationTip);
            $preparationTip->setSpice($this);
        }

        return $this;
    }

    public function removePreparationTip(PreparationTips $preparationTip): static
    {
        // set the owning side to null (unless already changed)
        if ($this->preparationTips->removeElement($preparationTip) && $preparationTip->getSpice() === $this) {
            $preparationTip->setSpice(null);
        }

        return $this;
    }

    public function getBenefits(): ?string
    {
        return $this->benefits;
    }

    public function setBenefits(?string $benefits): static
    {
        $this->benefits = $benefits;

        return $this;
    }
}
