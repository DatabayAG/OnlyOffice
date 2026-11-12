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

namespace ILIAS\Plugin\OnlyOffice\Utils;

use ilDateTime;
use ILIAS\Plugin\OnlyOffice\Repository\ObjectSettingsRepository;
use ilTimeZone;

class DateFetcher
{
    public static function editingPeriodIsFetchable($obj_id): bool
    {
        $object_settings = (new ObjectSettingsRepository())->read($obj_id);
        if ($object_settings && $object_settings->isLimitedPeriod()) {
            return $object_settings->isLimitedPeriod();
        }
        return false;
    }

    public static function fetchEditingPeriod($obj_id): string
    {
        global $DIC;

        $object_settings = (new ObjectSettingsRepository())->read($obj_id);

        if (!$object_settings) {
            return "-";
        }

        $converted_start_time = $object_settings->getStartTime()?->get(IL_CAL_FKT_DATE, 'd.m.Y H:i', $DIC->user()->getTimeZone());
        $converted_end_time = $object_settings->getEndTime()?->get(IL_CAL_FKT_DATE, 'd.m.Y H:i', $DIC->user()->getTimeZone());

        if (!$converted_start_time || !$converted_end_time) {
            return "-";
        }

        return "$converted_start_time - $converted_end_time";
    }

    public static function isWithinPotentialTimeLimit($obj_id): bool
    {
        $object_settings = (new ObjectSettingsRepository())->read($obj_id);

        if (!$object_settings) {
            return false;
        }

        $withinPotentialTimeLimit = true;

        if ($object_settings->isLimitedPeriod()) {
            $currentTime = new ilDateTime(time(), IL_CAL_UNIX);
            $startTime = new ilDateTime($object_settings->getStartTime()->get(IL_CAL_DATETIME), IL_CAL_DATETIME, ilTimeZone::UTC);
            $endTime = new ilDateTime($object_settings->getEndTime()->get(IL_CAL_DATETIME), IL_CAL_DATETIME, ilTimeZone::UTC);
            $isTimeWithin = ilDateTime::_within($currentTime, $startTime, $endTime);

            $withinPotentialTimeLimit = $isTimeWithin;
        }

        return $withinPotentialTimeLimit;
    }
}
