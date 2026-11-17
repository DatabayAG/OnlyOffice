<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

namespace ILIAS\Plugin\OnlyOffice\Model;

use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;

class FileTemplate
{
    private int $id;

    public function __construct(
        private readonly string $title,
        private readonly string $description,
        private readonly string $extension,
        private readonly FileCreationType $type,
        private readonly ResourceIdentification $rid,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getType(): FileCreationType
    {
        return $this->type;
    }

    public function getRid(): ResourceIdentification
    {
        return $this->rid;
    }
}
