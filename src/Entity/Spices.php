<?php

namespace App\Entity;

use App\Repository\SpicesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    private $id;

    #[ORM\ManyToOne(targetEntity: AromaticGroups::class, inversedBy: 'spices')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'id', name: 'aromaticGroups')]
    private $aromaticGroups;

    #[ORM\ManyToOne(targetEntity: SpicyType::class, inversedBy: 'spices')]
    #[ORM\JoinColumn(referencedColumnName: 'id', name: 'spicyType')]
    private $spicyType;

    #[ORM\Column(name: 'name', type: 'string', length: 255)]
    private $name;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(name: 'cooking', type: 'text', nullable: true)]
    private $cooking;

    #[ORM\Column(name: 'informations', type: 'text', nullable: true)]
    private $informations;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private $created_at;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private $updated_at;

    #[ORM\Column(name: 'deleted_at', type: 'datetime', nullable: true)]
    private $deleted_at;

    #[ORM\Column(name: 'file', type: 'string', length: 255, nullable: true)]
    private $file;

    /**
     * @Vich\UploadableField(mapping="spice_images", fileNameProperty="file", size="imageSize")
     * @var File|null
     */
    private $imageFile;

    /**
     *
     * @var int|null
     */
    #[ORM\Column(type: 'integer')]
    private $imageSize;

    #[ORM\ManyToMany(targetEntity: AromaticCompound::class, inversedBy: 'spices')]
    #[ORM\JoinColumn(referencedColumnName: 'id', name: 'aromaticsCompounds')]
    private $aromaticsCompounds;

    
    #[ORM\ManyToMany(targetEntity: AromaticCompound::class, inversedBy: 'secondary_spices')]
    #[ORM\JoinColumn(referencedColumnName: 'id', name: 'secondaryAromaticsCompounds')]
    #[ORM\JoinTable(name: 'secondary_spices_aromatic_compound')]
    private $secondary_aromatics_compounds;

    public function __construct()
    {
        $this->aromaticsCompounds = new ArrayCollection();
        $this->secondary_aromatics_compounds = new ArrayCollection();
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
    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updated_at = new \DateTime('now');
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
        if (!$this->aromaticsCompounds->contains($aromaticsCompounds)) {
            $this->aromaticsCompounds[] = $aromaticsCompounds;
        }

        return $this;
    }

    public function removeAromaticsCompounds(AromaticCompound $aromaticsCompounds): self
    {
        $this->aromaticsCompounds->removeElement($aromaticsCompounds);

        return $this;
    }

    public function __toString()
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
        if (!$this->secondary_aromatics_compounds->contains($secondaryAromaticsCompound)) {
            $this->secondary_aromatics_compounds[] = $secondaryAromaticsCompound;
        }

        return $this;
    }

    public function removeSecondaryAromaticsCompound(AromaticCompound $secondaryAromaticsCompound): self
    {
        $this->secondary_aromatics_compounds->removeElement($secondaryAromaticsCompound);

        return $this;
    }
}
