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

namespace ILIAS\Plugin\OnlyOffice\ObjectSettings;

use ilDateTime;
use ActiveRecord;
use arConnector;
use ILIAS\Plugin\OnlyOffice\Enum\OpenSetting;
use ilOnlyOfficePlugin;

class ObjectSettings extends ActiveRecord
{
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
    protected string $open_setting = OpenSetting::ILIAS->value;

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

    /**
     * @param string $field_name
     */
    public function wakeUp($field_name, $field_value): bool|int|null
    {
        return match ($field_name) {
            "obj_id" => (int) $field_value,
            "is_online", "allow_edit" => (bool) $field_value,
            default => null,
        };
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

    public function getOpen(): OpenSetting
    {
        return OpenSetting::from($this->open_setting);
    }

    public function setOpen(OpenSetting $openSetting): void
    {
        $this->open_setting = $openSetting->value;
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
