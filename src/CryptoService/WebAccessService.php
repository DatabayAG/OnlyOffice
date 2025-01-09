<?php

namespace srag\Plugins\OnlyOffice\CryptoService;

use ilWACSignedPath;
use ilWebAccessChecker;
use ilFileDelivery;

/**
 * Appends a token to a given URL to grant access to the location.
 */
class WebAccessService
{
    public static function getWACUrl(string $url): string
    {
        ilWACSignedPath::setTokenMaxLifetimeInSeconds(ilWACSignedPath::MAX_LIFETIME);
        $file_path = ilWACSignedPath::signFile(\ilFileUtils::getWebspaceDir() . $url);
        $file_path .= '&' . ilWebAccessChecker::DISPOSITION . '=' . ilFileDelivery::DISP_ATTACHMENT;
        return $file_path;

    }

}
