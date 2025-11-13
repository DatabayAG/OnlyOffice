<?php

namespace ILIAS\Plugin\OnlyOffice\Utils;

use ILIAS\Plugin\OnlyOffice\Repository;

trait OnlyOfficeTrait
{
    protected static function onlyOffice(): Repository
    {
        return Repository::getInstance();
    }
}
