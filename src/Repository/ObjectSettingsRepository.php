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

namespace ILIAS\Plugin\OnlyOffice\Repository;

use ilDateTime;
use ilDBConstants;
use ilDBInterface;
use ILIAS\Plugin\OnlyOffice\Enum\OpenSetting;
use ILIAS\Plugin\OnlyOffice\Model\ObjectSettings;

class ObjectSettingsRepository
{
    public const string TABLE_NAME = "rep_robj_xono_set";

    protected ilDBInterface $db;

    public function __construct(?ilDBInterface $db = null)
    {
        global $DIC;
        $this->db = $db ?: $DIC->database();
    }

    public function read(int $obj_id): ?ObjectSettings
    {
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE obj_id = %s",
            [ilDBConstants::T_INTEGER],
            [$obj_id]
        );

        $row = $this->db->fetchAssoc($result);

        if (!$row) {
            return null;
        }

        return $this->map($row);
    }

    public function create(ObjectSettings $settings): bool
    {
        return $this->db->insert(
            self::TABLE_NAME,
            [
                "obj_id" => [ilDBConstants::T_INTEGER, $settings->getObjId()],
                "title" => [ilDBConstants::T_TEXT, $settings->getTitle()],
                "desc" => [ilDBConstants::T_TEXT, $settings->getDescription()],
                "open_setting" => [ilDBConstants::T_TEXT, $settings->getOpenSetting()->value],
                "is_online" => [ilDBConstants::T_INTEGER, $settings->isOnline()],
                "allow_edit" => [ilDBConstants::T_INTEGER, $settings->isAllowEdit()],
                "limited_period" => [ilDBConstants::T_INTEGER, $settings->isLimitedPeriod()],
                "start_time" => [ilDBConstants::T_TIMESTAMP, $settings->getStartTime()?->get(IL_CAL_DATETIME)],
                "end_time" => [ilDBConstants::T_TIMESTAMP, $settings->getEndTime()?->get(IL_CAL_DATETIME)]
            ]
        ) === 1;
    }

    public function update(ObjectSettings $settings): bool
    {
        return $this->db->update(
            self::TABLE_NAME,
            [
                "title" => [ilDBConstants::T_TEXT, $settings->getTitle()],
                "desc" => [ilDBConstants::T_TEXT, $settings->getDescription()],
                "open_setting" => [ilDBConstants::T_TEXT, $settings->getOpenSetting()->value],
                "is_online" => [ilDBConstants::T_INTEGER, $settings->isOnline()],
                "allow_edit" => [ilDBConstants::T_INTEGER, $settings->isAllowEdit()],
                "limited_period" => [ilDBConstants::T_INTEGER, $settings->isLimitedPeriod()],
                "start_time" => [ilDBConstants::T_TIMESTAMP, $settings->getStartTime()?->get(IL_CAL_DATETIME)],
                "end_time" => [ilDBConstants::T_TIMESTAMP, $settings->getEndTime()?->get(IL_CAL_DATETIME)]
            ],
            ["obj_id" => [ilDBConstants::T_INTEGER, $settings->getObjId()]]
        ) === 1;
    }

    public function store(ObjectSettings $settings): bool
    {
        if ($this->exists($settings->getObjId())) {
            return $this->update($settings);
        }

        return $this->create($settings);
    }

    public function delete(ObjectSettings $settings): bool
    {
        return $this->deleteById($settings->getObjId());
    }

    public function deleteById(int $obj_id): bool
    {
        return $this->db->manipulateF(
            "DELETE FROM " . self::TABLE_NAME . " WHERE obj_id = %s",
            [ilDBConstants::T_INTEGER],
            [$obj_id]
        ) === 1;
    }

    public function exists(int $obj_id): bool
    {
        $result = $this->db->queryF(
            "SELECT EXISTS(SELECT 1 FROM " . self::TABLE_NAME . " WHERE obj_id = %s) AS does_exist",
            [ilDBConstants::T_INTEGER],
            [$obj_id]
        );

        return (bool) $this->db->fetchAssoc($result)["does_exist"];
    }

    private function map(array $row): ObjectSettings
    {
        return new ObjectSettings(
            (int) $row["obj_id"],
            $row["title"],
            $row["desc"],
            OpenSetting::tryFrom($row["open_setting"]) ?? OpenSetting::ILIAS,
            (bool) $row["is_online"],
            (bool) $row["allow_edit"],
            (bool) $row["limited_period"],
            $row["start_time"] ? new ilDateTime($row["start_time"], IL_CAL_DATETIME) : null,
            $row["end_time"] ? new ilDateTime($row["end_time"], IL_CAL_DATETIME) : null,
        );
    }
}
