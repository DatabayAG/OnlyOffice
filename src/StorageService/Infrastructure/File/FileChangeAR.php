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

namespace ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File;

use ActiveRecord;
use Exception;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Data\UUID\Factory as UUIDFactory;

/**
 * Stores the changes between file versions
 * such that they can easily be passed back
 * to the OnlyOffice Server
 */
class FileChangeAR extends ActiveRecord
{
    public const TABLE_NAME = 'xono_file_change';

    public function getConnectorContainerName(): string
    {
        return self::TABLE_NAME;
    }

    /**
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_is_primary   true
     * @con_sequence     true
     */
    protected ?int $change_id;

    /**
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    256
     */
    protected Uuid $file_uuid;
    /**
     * @con_has_field    true
     * @con_fieldtype    integer
     */
    protected int $version;

    /**
     * @con_has_field true
     * @con_fieldtype clob
     */
    protected string $changes_object_string;
    /**
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    64
     */
    protected string $server_version;
    /**
     * @con_has_field true
     * @con_fieldtype text
     * @con_length    256
     */
    protected string $changes_url;

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

    public function setChangesObjectString(string $changesObjectString): void
    {
        $this->changes_object_string = $changesObjectString;
    }

    public function getChangesObjectString(): string
    {
        return $this->changes_object_string;
    }

    public function setServerVersion(string $serverVersion): void
    {
        $this->server_version = $serverVersion;
    }

    public function getServerVersion(): string
    {
        return $this->server_version;
    }

    public function setChangesUrl(string $changesUrl): void
    {
        $this->changes_url = $changesUrl;
    }

    public function getChangesUrl(): string
    {
        return $this->changes_url;
    }

    public function sleep($field_name): ?string
    {
        switch ($field_name) {
            case 'file_uuid':
                return $this->file_uuid->toString();
            default:
                return parent::sleep($field_name);
        }
    }

    /**
     * @throws Exception
     */
    public function wakeUp($field_name, $field_value): ?Uuid
    {
        switch ($field_name) {
            case 'file_uuid':
                return (new UUIDFactory())->fromString($field_value);
            default:
                return parent::wakeUp($field_name, $field_value);
        }
    }

}
