<?php

namespace srag\Plugins\OnlyOffice\StorageService\Infrastructure\File;

use srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common\UUID;
use srag\Plugins\OnlyOffice\StorageService\DTO\FileChange;

interface FileChangeRepository
{
    public const DEFAULT_SERVER_VERSION = '6.3.1';

    /**
     * @return mixed
     */
    public function create(
        UUID $file_uuid,
        int $version,
        string $changesObjectString,
        string $serverVersion,
        string $changesUrl
    );

    public function getAllChanges(string $uuid): array;

    public function getChange(string $uuid, int $version): FileChange;

}
