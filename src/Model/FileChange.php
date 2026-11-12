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

use ILIAS\Data\UUID\Uuid;

class FileChange
{
    public const string DEFAULT_SERVER_VERSION = "6.3.1";

    private int $change_id;

    public function __construct(
        private readonly Uuid $file_uuid,
        private readonly int $version,
        private readonly string $changes_object_string,
        private readonly string $changes_url,
        private string $server_version = self::DEFAULT_SERVER_VERSION
    ) {
    }

    public function getChangeId(): int
    {
        return $this->change_id;
    }

    public function setChangeId(int $change_id): self
    {
        $this->change_id = $change_id;
        return $this;
    }

    public function getFileUuid(): Uuid
    {
        return $this->file_uuid;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getChangesObjectString(): string
    {
        return $this->changes_object_string;
    }

    public function getChangesUrl(): string
    {
        return $this->changes_url;
    }

    public function getServerVersion(): string
    {
        return $this->server_version;
    }

    public function setServerVersion(string $server_version): self
    {
        $this->server_version = $server_version;
        return $this;
    }

}
