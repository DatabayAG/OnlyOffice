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

class FileSanitizer
{
    public static function sanitizeFileName(string $file_name_to_sanitize): string
    {
        $file_name_to_sanitize = preg_replace('/ä+/u', 'ae', $file_name_to_sanitize);
        $file_name_to_sanitize = preg_replace('/ü+/u', 'ue', $file_name_to_sanitize);
        $file_name_to_sanitize = preg_replace('/ö+/u', 'oe', $file_name_to_sanitize);
        $file_name_to_sanitize = preg_replace('/ß+/u', 'ss', $file_name_to_sanitize);
        $file_name_to_sanitize = preg_replace('/\s+/', '_', $file_name_to_sanitize);
        return preg_replace('/[^a-zA-Z0-9\-_]+/', '', $file_name_to_sanitize);
    }
}
