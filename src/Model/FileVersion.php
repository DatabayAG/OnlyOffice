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

use ilDateTime;
use ILIAS\Data\UUID\Uuid;
use ilObjUser;
use JsonSerializable;

class FileVersion implements JsonSerializable
{
    public const int FIRST_VERSION = 1;

    private int $id;

    public function __construct(
        private readonly Uuid $file_uuid,
        private readonly int $user_id,
        private readonly ilDateTime $created_at,
        private readonly string $url,
        private int $version = -1,
    ) {
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFileUuid(): Uuid
    {
        return $this->file_uuid;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getCreatedAt(): ilDateTime
    {
        return $this->created_at;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function jsonSerialize(): array
    {
        $user = new ilObjUser($this->user_id);
        return [
            'version' => $this->version,
            'createdAt' => $this->created_at->get(1),
            'userId' => $this->user_id,
            'user' => $user->getPublicName(),
            'url' => $this->url,
            'uuid' => $this->file_uuid->toString()
        ];
    }
}
