<?php

namespace srag\Plugins\OnlyOffice\StorageService\DTO;

use srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common\UUID;

class FileChange
{
    protected int $change_id;
    protected UUID $file_uuid;
    protected int $version;
    protected string $changesObjectString;
    protected string $serverVersion;
    protected string $changesUrl;

    public function __construct(
        int $change_id,
        UUID $file_uuid,
        int $version,
        string $changesObjectString,
        string $serverVersion,
        string $changesUrl
    ) {
        $this->change_id = $change_id;
        $this->file_uuid = $file_uuid;
        $this->version = $version;
        $this->changesObjectString = $changesObjectString;
        $this->serverVersion = $serverVersion;
        $this->changesUrl = $changesUrl;
    }

    public function setChangeId(int $change_id): void
    {
        $this->change_id = $change_id;
    }

    public function getChangeId(): int
    {
        return $this->change_id;
    }

    public function setFileUuid(UUID $file_uuid): void
    {
        $this->file_uuid = $file_uuid;
    }

    public function getFileUuid(): UUID
    {
        return $this->file_uuid;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setChangesObjectString(string $changes): void
    {
        $this->changesObjectString = $changes;
    }

    public function getChangesObjectString(): string
    {
        return $this->changesObjectString;
    }

    public function setServerVersion(string $serverVersion): void
    {
        $this->serverVersion = $serverVersion;
    }

    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    public function setChangesUrl(string $changesUrl): void
    {
        $this->changesUrl = $changesUrl;
    }

    public function getChangesUrl(): string
    {
        return $this->changesUrl;
    }

}
