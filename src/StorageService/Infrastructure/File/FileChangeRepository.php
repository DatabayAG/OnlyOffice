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

use ILIAS\Data\UUID\Uuid;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\FileChange;

interface FileChangeRepository
{
    public const DEFAULT_SERVER_VERSION = '6.3.1';

    /**
     * @return mixed
     */
    public function create(
        Uuid $file_uuid,
        int $version,
        string $changesObjectString,
        string $serverVersion,
        string $changesUrl
    );

    public function getAllChanges(string $uuid): array;

    public function getChange(string $uuid, int $version): FileChange;

}
