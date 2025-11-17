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

namespace ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File;

use ActiveRecord;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\File;

interface FileRepository
{
    public function create(
        Uuid   $file_uuid,
        int    $obj_id,
        string $title,
        string $file_type,
        string $mime_type
    ): void;

    public function getFile(int $obj_id): ?File;

    public function getAR(int $file_id): ActiveRecord;

    public function getAllFiles();

}
