<?php

namespace srag\Plugins\OnlyOffice\InfoService;

use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;

/**
 * Used to access information using OnlyOfficeTrait.
 */
class InfoService
{
    use OnlyOfficeTrait;

    public static function getOpenSetting(int $file_id): string
    {
        return self::onlyOffice()->objectSettings()->getObjectSettingsById($file_id)->getOpen();
    }

    final public static function getOnlyOfficeUrl(): string
    {
        return self::onlyOffice()->config()->getValue("onlyoffice_url");
    }

    final public static function getSecret(): string
    {
        return self::onlyOffice()->config()->getValue("onlyoffice_secret");
    }

    final public static function getNumberOfVersions(): int
    {
        return self::onlyOffice()->config()->getValue("number_of_versions");
    }

}
