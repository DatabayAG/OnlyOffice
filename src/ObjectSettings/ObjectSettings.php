<?php

namespace srag\Plugins\OnlyOffice\ObjectSettings;

use ilDateTime;
use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use ActiveRecord;
use arConnector;
use ilOnlyOfficePlugin;
use srag\DIC\OnlyOffice\DICTrait;

class ObjectSettings extends ActiveRecord
{
    use DICTrait;
    use OnlyOfficeTrait;

    public const TABLE_NAME = "rep_robj_xono_set";
    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;

    public function getConnectorContainerName(): string
    {
        return self::TABLE_NAME;
    }

    public static function returnDbTableName(): string
    {
        return self::TABLE_NAME;
    }

    /**
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       8
     * @con_is_notnull   true
     * @con_is_primary   true
     */
    protected ?int $obj_id;

    /**
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_is_notnull true
     */
    protected string $title;

    /**
     * @con_has_field true
     * @con_fieldtype text
     */
    protected string $desc;

    /**
     * @con_has_field    true
     * @con_fieldtype    integer
     * @con_length       1
     * @con_is_notnull   true
     */
    protected bool $is_online = false;

    /**
     * @con_has_field  true
     * @con_fieldtype  text
     * @con_length     10
     * @con_is_notnull true
     */
    protected string $open_setting = "ilias";

    /**
     * @var bool
     * Indicates whether all users are allowed to edit or not
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     1
     * @con_is_notnull true
     */
    protected bool $allow_edit;

    /**
     * @var ilDateTime
     * @db_has_field         true
     * @db_fieldtype         timestamp
     */
    protected ?string $start_time = null;

    /**
     * @var ilDateTime
     * @db_has_field         true
     * @db_fieldtype         timestamp
     */
    protected ?string $end_time = null;

    /**
     * @con_has_field  true
     * @con_fieldtype  integer
     * @con_length     1
     */
    protected bool $limited_period;

    /**
     * @param int              $primary_key_value
     */
    public function __construct(/*int*/ $primary_key_value = 0, arConnector $connector = null)
    {
        parent::__construct($primary_key_value, $connector);
    }

    public function sleep(/*string*/ $field_name): ?int
    {
        $field_value = $this->{$field_name};

        switch ($field_name) {
            case "is_online":
            case "allow_edit":
                return ($field_value ? 1 : 0);
            default:
                return null;
        }
    }

    public function wakeUp(/*string*/ $field_name, $field_value)
    {
        switch ($field_name) {
            case "obj_id":
                return intval($field_value);
            case "is_online":
            case "allow_edit":
                return boolval($field_value);
            default:
                return null;
        }
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

    public function getDescription(): string
    {
        return $this->desc;
    }

    public function setDescription(string $description): void
    {
        $this->desc = $description;
    }

    public function allowEdit(): bool
    {
        return $this->allow_edit;
    }

    public function setAllowEdit(bool $allow_edit): void
    {
        $this->allow_edit = $allow_edit;
    }

    public function isOnline(): bool
    {
        return $this->is_online;
    }

    public function setOnline(bool $is_online = true): void
    {
        $this->is_online = $is_online;
    }

    public function getOpen(): string
    {
        return $this->open_setting;
    }

    public function setOpen(string $open): void
    {
        $this->open_setting = $open;
    }

    public function getStartTime(): ?string
    {
        return $this->start_time;
    }

    public function setStartTime(string $start_time): void
    {
        $this->start_time = $start_time;
    }

    public function getEndTime(): ?string
    {
        return $this->end_time;
    }

    public function setEndTime(string $end_time): void
    {
        $this->end_time = $end_time;
    }

    public function isLimitedPeriod(): ?bool
    {
        return $this->limited_period;
    }

    public function setLimitedPeriod(bool $limited_period): void
    {
        $this->limited_period = $limited_period;
    }
}
