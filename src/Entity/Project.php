<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Guid\Guid;
use Ramsey\Uuid\Guid\GuidInterface;
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Resource>
     */

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    /**
     * @var Collection<int, Resource>
     */
    #[ORM\OneToMany(targetEntity: Resource::class, mappedBy: 'project',cascade: ['persist', 'remove'])]
    private Collection $resources;

    #[ORM\Column(type: Types::GUID)]
    private ?string $uuid = null;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    private ?User $owner = null;

    /**
     * @var Collection<int, ProjectMessage>
     */
    #[ORM\ManyToMany(targetEntity: ProjectMessage::class, mappedBy: 'projects')]
    private Collection $projectMessages;

    public function __construct()
    {
        $this->resources = new ArrayCollection();
        $this->projectMessages = new ArrayCollection();
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
     * @return Collection<int, Resource>
     */
    public function getResources(): Collection
    {
        return $this->resources;
    }

    public function addResource(Resource $resource): static
    {
        if (!$this->resources->contains($resource)) {
            $this->resources->add($resource);
            $resource->setProject($this);
        }

        return $this;
    }

    public function removeResource(Resource $resource): static
    {
        if ($this->resources->removeElement($resource)) {
            // set the owning side to null (unless already changed)
            if ($resource->getProject() === $this) {
                $resource->setProject(null);
            }
        }

        return $this;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    #[ORM\PrePersist]
    public function autoSaveUuid(): void
    {
        if (null === $this->uuid) {
            $this->uuid = Guid::uuid4();
        }
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, ProjectMessage>
     */
    public function getProjectMessages(): Collection
    {
        return $this->projectMessages;
    }

    public function addProjectMessage(ProjectMessage $projectMessage): static
    {
        if (!$this->projectMessages->contains($projectMessage)) {
            $this->projectMessages->add($projectMessage);
            $projectMessage->addProject($this);
        }

        return $this;
    }

    public function removeProjectMessage(ProjectMessage $projectMessage): static
    {
        if ($this->projectMessages->removeElement($projectMessage)) {
            $projectMessage->removeProject($this);
        }

        return $this;
    }
}
