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
    public static function sanitizeFileName(string $fileNameToSanitize): string
    {
        $fileNameToSanitize = preg_replace('/ä+/u', 'ae', $fileNameToSanitize);
        $fileNameToSanitize = preg_replace('/ü+/u', 'ue', $fileNameToSanitize);
        $fileNameToSanitize = preg_replace('/ö+/u', 'oe', $fileNameToSanitize);
        $fileNameToSanitize = preg_replace('/ß+/u', 'ss', $fileNameToSanitize);
        $fileNameToSanitize = preg_replace('/\s+/', '_', $fileNameToSanitize);
        return preg_replace('/[^a-zA-Z0-9\-_]+/', '', $fileNameToSanitize);
    }
}
