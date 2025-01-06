<?php

namespace srag\Plugins\OnlyOffice\Utils;

use ilDateTime;
use srag\DIC\OnlyOffice\DICTrait;
use ilTimeZone;

class DateFetcher
{
    use DICTrait;
    use OnlyOfficeTrait;

    public static function editingPeriodIsFetchable($obj_id): bool
    {
        $object_settings = self::onlyOffice()->objectSettings()->getObjectSettingsById($obj_id);
        if (!is_null($object_settings) && !is_null($object_settings->isLimitedPeriod())) {
            return $object_settings->isLimitedPeriod();
        }
        return false;
    }

    public static function fetchEditingPeriod($obj_id): string
    {
        $object_settings = self::onlyOffice()->objectSettings()->getObjectSettingsById($obj_id);
        $converted_start_time = new ilDateTime($object_settings->getStartTime(), IL_CAL_DATETIME, ilTimeZone::UTC);
        $converted_start_time = $converted_start_time->get(IL_CAL_FKT_DATE, 'd.m.Y H:i', self::dic()->user()->getTimeZone());
        $converted_end_time = new ilDateTime($object_settings->getEndTime(), IL_CAL_DATETIME, ilTimeZone::UTC);
        $converted_end_time = $converted_end_time->get(IL_CAL_FKT_DATE, 'd.m.Y H:i', self::dic()->user()->getTimeZone());
        return sprintf("%s - %s", $converted_start_time, $converted_end_time);
    }

    public static function isWithinPotentialTimeLimit($obj_id): bool
    {
        $object_settings = self::onlyOffice()->objectSettings()->getObjectSettingsById($obj_id);
        $withinPotentialTimeLimit = true;
        if ($object_settings->isLimitedPeriod()) {
            $currentTime = new ilDateTime(time(), IL_CAL_UNIX);
            $startTime = new ilDateTime($object_settings->getStartTime(), IL_CAL_DATETIME, ilTimeZone::UTC);
            $endTime = new ilDateTime($object_settings->getEndTime(), IL_CAL_DATETIME, ilTimeZone::UTC);
            $isTimeWithin = ilDateTime::_within($currentTime, $startTime, $endTime);

            $withinPotentialTimeLimit = $isTimeWithin;
        }

        return $withinPotentialTimeLimit;
    }
}
