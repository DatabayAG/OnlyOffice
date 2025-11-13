<?php

namespace ILIAS\Plugin\OnlyOffice\InfoService;

use ILIAS\Plugin\OnlyOffice\Repository;

/**
 * Used to access information using OnlyOfficeTrait.
 */
class InfoService
{

    public static function getOpenSetting(int $file_id): string
    {
        return Repository::getInstance()->objectSettings()->getObjectSettingsById($file_id)->getOpen();
    }

}
