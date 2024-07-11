<?php
// src/Entity/Folder.php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Document;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'folder', indexes: [
    new ORM\Index(name: 'folder_owner_idx', columns: ['owner_id']),
    new ORM\Index(name: 'folder_parent_idx', columns: ['parent_id']),
    new ORM\Index(name: 'folder_created_at_idx', columns: ['created_at'])
])]
class Folder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'subFolders')]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['remove'])]
    private Collection $subFolders;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;
    

    #[ORM\OneToMany(mappedBy: 'folder', targetEntity: Document::class)]
    private Collection $documents;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->subFolders = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters et setters...
    
    public function getId(): ?int
    {
        return $this->id;
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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, Folder>
     */
    public function getSubFolders(): Collection
    {
        return $this->subFolders;
    }

    public function addSubFolder(Folder $subFolder): self
    {
        if (!$this->subFolders->contains($subFolder)) {
            $this->subFolders->add($subFolder);
            $subFolder->setParent($this);
        }

        return $this;
    }

    public function removeSubFolder(Folder $subFolder): self
    {
        if ($this->subFolders->removeElement($subFolder)) {
            // set the owning side to null (unless already changed)
            if ($subFolder->getParent() === $this) {
                $subFolder->setParent(null);
            }
        }

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): self
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setFolder($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): self
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getFolder() === $this) {
                $document->setFolder(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
