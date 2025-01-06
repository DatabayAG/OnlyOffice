<?php

namespace srag\Plugins\OnlyOffice\StorageService\Infrastructure\File;

use ActiveRecord;
use Exception;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common\UUID;

class FileAR extends ActiveRecord
{
    public const TABLE_NAME = 'xono_file';
    public function getConnectorContainerName(): string
    {
        return self::TABLE_NAME;
    }

    /**
     * @con_has_field    true
     * @con_fieldtype    text
     * @con_length       256
     * @con_is_unique    true
     * @con_is_notnull   true
     */
    protected UUID $uuid;

    /**
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     * @con_is_primary   true
     */
    protected ?int $obj_id;

    /**
     * @db_has_field        true
     * @db_fieldtype        text
     * @con_is_notnull      true
     * @db_length           256
     */
    protected string $title;

    /**
     * @db_has_field        true
     * @db_fieldtype        text
     * @con_is_notnull      true
     * @db_length           256
     */
    protected string $file_type;

    /**
     * @db_has_field        true
     * @db_fieldtype        text
     * @db_length           256
     */
    protected string $mime_type;

    public function getUUID(): UUID
    {
        return $this->uuid;
    }

    public function setUUID(UUID $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getId(): UUID
    {
        return $this->id;
    }

    public function setId(UUID $id): void
    {
        $this->id = $id;
    }

    public function getObjId(): int
    {
        return $this->obj_id;
    }

    public function setObjId(int $obj_id): void
    {
        $this->obj_id = $obj_id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getFileType(): string
    {
        return $this->file_type;
    }

    public function setFileType(string $file_type): void
    {
        $this->file_type = $file_type;
    }

    public function getMimeType(): string
    {
        return $this->mime_type;
    }

    public function setMimeType(string $mime_type): void
    {
        $this->mime_type = $mime_type;
    }

    public function sleep($field_name): ?string
    {
        switch ($field_name) {
            case 'uuid':
                return $this->uuid->asString();
            default:
                return parent::sleep($field_name);
        }
    }

    /**
     * @throws Exception
     */
    public function wakeUp($field_name, $field_value): ?UUID
    {
        switch ($field_name) {
            case 'uuid':
                return new UUID($field_value);
            default:
                return parent::wakeUp($field_name, $field_value);
        }
    }
}
