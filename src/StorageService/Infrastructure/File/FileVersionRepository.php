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

use ilDateTime;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\FileVersion;

interface FileVersionRepository
{
    public function create(Uuid $file_uuid, int $user_id, ilDateTime $created_at, string $url, int $version = -1): int;

    public function getByObjectID(int $object_id): FileVersion;

    public function getAllVersions(Uuid $file_uuid): array;

    public function getLatestVersion(Uuid $file_uuid): ?FileVersion;

    public function getPreviousVersion(string $uuid, int $version): FileVersion;
}
