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

    final public static function getOnlyOfficeUrl(): string
    {
        return Repository::getInstance()->config()->getValue("onlyoffice_url");
    }

    final public static function getSecret(): string
    {
        return Repository::getInstance()->config()->getValue("onlyoffice_secret");
    }

    final public static function getNumberOfVersions(): int
    {
        return Repository::getInstance()->config()->getValue("number_of_versions");
    }

}
