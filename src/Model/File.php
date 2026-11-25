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

namespace ILIAS\Plugin\OnlyOffice\Model;

use ILIAS\Data\UUID\Uuid;

class File
{
    public function __construct(
        private readonly int $obj_id,
        private readonly Uuid $uuid,
        private readonly string $title,
        private readonly string $file_type,
        private readonly string $mime_type,
    ) {
    }

    public function getObjId(): int
    {
        return $this->obj_id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getFileType(): string
    {
        return $this->file_type;
    }

    public function getMimeType(): string
    {
        return $this->mime_type;
    }
}
