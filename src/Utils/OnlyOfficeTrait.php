<?php

namespace srag\Plugins\OnlyOffice\Utils;

use srag\Plugins\OnlyOffice\Repository;

trait OnlyOfficeTrait
{
    protected static function onlyOffice(): Repository
    {
        return Repository::getInstance();
    }
}
