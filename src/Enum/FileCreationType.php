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

namespace ILIAS\Plugin\OnlyOffice\Enum;

enum FileCreationType: string
{
    case TEXT = "text";

    case TABLE = "table";
    case PRESENTATION = "presentation";
    case NONE = "";

    public function toDocumentType(): string
    {
        return match ($this) {
            self::TEXT => "docx",
            self::TABLE => "xlsx",
            self::PRESENTATION => "pptx",
        };
    }

    public function getEditorType(): string
    {
        return match ($this) {
            self::TEXT => "word",
            self::TABLE => "cell",
            self::PRESENTATION => "slide",
        };
    }
}
