<?php

namespace ILIAS\Plugin\OnlyOffice\StorageService\DTO;

use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;

class FileTemplate
{
    protected string $title;
    protected string $description;
    protected string $extension;
    protected string $path;
    protected FileCreationType $type;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getType(): FileCreationType
    {
        return $this->type;
    }

    public function setType(FileCreationType $type): void
    {
        $this->type = $type;
    }

}
