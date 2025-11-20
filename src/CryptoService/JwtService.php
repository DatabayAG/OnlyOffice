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

namespace ILIAS\Plugin\OnlyOffice\CryptoService;

/**
 * Encodes a given payload using a given key to a JsonWebToken or
 * decodes a given token using a given key.
 */
class JwtService
{
    public static function jwtEncode($payload, $key): string
    {
        $header = [
            "alg" => "HS256",
            "typ" => "JWT"
        ];
        $payloadString = json_encode($payload, JSON_THROW_ON_ERROR);
        $encHeader = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encPayload = self::base64UrlEncode($payloadString);
        $hash = self::base64UrlEncode(self::calculateHash($encHeader, $encPayload, $key));

        return "$encHeader.$encPayload.$hash";
    }

    public static function jwtDecode($token, $key): string
    {

        $split = explode(".", $token);
        if (count($split) !== 3) {
            return "";
        }

        $hash = self::base64UrlEncode(self::calculateHash($split[0], $split[1], $key));

        if (strcmp($hash, $split[2]) !== 0) {
            return "";
        }
        return self::base64UrlDecode($split[1]);
    }

    protected static function calculateHash($encHeader, $encPayload, $key): string
    {
        return hash_hmac("sha256", "$encHeader.$encPayload", $key, true);
    }

    protected static function base64UrlEncode($str): string
    {
        return str_replace(["+", "/"], ["-", "_"], trim(base64_encode($str), "="));
    }

    protected static function base64UrlDecode($payload): string
    {
        $b64 = str_replace(["-", "_"], ["+", "/"], $payload);
        switch (strlen($b64) % 4) {
            case 2:
                $b64 .= "==";
                break;
            case 3:
                $b64 .= "=";
                break;
        }
        return base64_decode($b64);
    }
}
