<?php

namespace srag\Plugins\OnlyOffice\CryptoService;

use ilWACSignedPath;
use ilWebAccessChecker;
use ilUtil;
use ilFileDelivery;

/**
 * Class WebAccessService
 *
 * Appends a token to a given URL to grant access to the location.
 *
 * @author      Sophie Pfister <sophie@fluxlabs.ch>
 */
class WebAccessService
{

    public static function getWACUrl(string $url) : string
    {
        ilWACSignedPath::setTokenMaxLifetimeInSeconds(ilWACSignedPath::MAX_LIFETIME);
        $file_path = ilWACSignedPath::signFile(\ilFileUtils::getWebspaceDir() . $url);
        $file_path .= '&' . ilWebAccessChecker::DISPOSITION . '=' . ilFileDelivery::DISP_ATTACHMENT;
        return $file_path;

    }

}