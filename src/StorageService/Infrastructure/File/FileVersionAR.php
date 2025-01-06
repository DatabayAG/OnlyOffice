<?php

namespace srag\Plugins\OnlyOffice\StorageService\Infrastructure\File;

use ActiveRecord;
use ilDateTime;
use ilDateTimeException;
use ilTimeZone;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common\UUID;

class FileVersionAR extends ActiveRecord
{
    public const TABLE_NAME = 'xono_file_version';

    public function getConnectorContainerName(): string
    {
        return self::TABLE_NAME;
    }

    /**
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     * @con_is_notnull   true
     * @con_is_primary   true
     * @con_sequence     true
     */
    protected ?int $id;
    /**
     * @con_has_field    true
     * @con_fieldtype    text
     * @con_length       256
     * @con_is_notnull   true
     */
    protected UUID $file_uuid;
    /**
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     * @con_is_notnull   true
     */
    protected int $version;
    /**
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     * @con_is_notnull   true
     */
    protected int $user_id;
    /**
     * @db_has_field         true
     * @db_fieldtype         timestamp
     * @con_is_notnull       true
     */
    protected ilDateTime $created_at;
    /**
     * @db_has_field         true
     * @db_fieldtype         text
     * @con_is_notnull       true
     */
    protected string $url;

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getFileUuid(): UUID
    {
        return $this->file_uuid;
    }

    public function setFileUuid(UUID $file_uuid): void
    {
        $this->file_uuid = $file_uuid;
    }

    public function getCreatedAt(): ilDateTime
    {
        $this->created_at->switchTimeZone(ilTimeZone::UTC);
        return $this->created_at;
    }

    public function setCreatedAt(ilDateTime $created_at): void
    {
        $this->created_at = $created_at;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function sleep($field_name)
    {
        switch ($field_name) {
            case 'file_uuid':
                return $this->file_uuid->asString();
            case 'created_at':
                return $this->created_at->get(IL_CAL_FKT_DATE, 'Y-m-d H:i:s');
            default:
                return parent::sleep($field_name);
        }
    }

    /**
     * @throws ilDateTimeException
     */
    public function wakeUp($field_name, $field_value)
    {
        switch ($field_name) {
            case 'file_uuid':
                return new UUID($field_value);
            case 'created_at':
                return new ilDateTime($field_value, IL_CAL_DATE);
            default:
                return parent::wakeUp($field_name, $field_value);
        }
    }
}
