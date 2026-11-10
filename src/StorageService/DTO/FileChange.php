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

namespace ILIAS\Plugin\OnlyOffice\StorageService\DTO;

use ILIAS\Data\UUID\Uuid;

class FileChange
{
    protected Uuid $file_uuid;

    public function __construct(
        protected int $change_id,
        Uuid $file_uuid,
        protected int $version,
        protected string $changesObjectString,
        protected string $serverVersion,
        protected string $changesUrl
    ) {
        $this->file_uuid = $file_uuid;
    }

    public function setChangeId(int $change_id): void
    {
        $this->change_id = $change_id;
    }

    public function getChangeId(): int
    {
        return $this->change_id;
    }

    public function setFileUuid(Uuid $file_uuid): void
    {
        $this->file_uuid = $file_uuid;
    }

    public function getFileUuid(): Uuid
    {
        return $this->file_uuid;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setChangesObjectString(string $changes): void
    {
        $this->changesObjectString = $changes;
    }

    public function getChangesObjectString(): string
    {
        return $this->changesObjectString;
    }

    public function setServerVersion(string $serverVersion): void
    {
        $this->serverVersion = $serverVersion;
    }

    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    public function setChangesUrl(string $changesUrl): void
    {
        $this->changesUrl = $changesUrl;
    }

    public function getChangesUrl(): string
    {
        return $this->changesUrl;
    }

}
