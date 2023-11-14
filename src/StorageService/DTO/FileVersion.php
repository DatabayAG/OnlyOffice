<?php

namespace srag\Plugins\OnlyOffice\StorageService\DTO;

use ilDateTime;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common\UUID;

/**
 * Class FileVersion
 * @package srag\Plugins\OnlyOffice\StorageService\DTO
 * @author  Theodor Truffer <theo@fluxlabs.ch>
 */
class FileVersion implements \JsonSerializable
{

    const FIRST_VERSION = 1;
    protected int $version;
    protected ilDateTime $created_at;
    protected int $user_id;
    protected string $url;
    protected UUID $file_uuid;

    /**
     * FileVersion constructor.
     * @param int        $version
     * @param ilDateTime $created_at
     * @param int        $user_id
     * @param string     $url
     */
    public function __construct(int $version, ilDateTime $created_at, int $user_id, string $url, UUID $file_uuid)
    {
        $this->version = $version;
        $this->created_at = $created_at;
        $this->user_id = $user_id;
        $this->url = $url;
        $this->file_uuid = $file_uuid;
    }

    public function getVersion() : int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getCreatedAt() : ilDateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(ilDateTime $date): void
    {
        $this->created_at = $date;
    }

    public function getUserId() : int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getUrl() : string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getFileUuid() : UUID
    {
        return $this->file_uuid;
    }

    public function setFileUuid(UUID $uuid): void
    {
        $this->file_uuid = $uuid;
    }

    public function jsonSerialize(): array
    {
        global $DIC;
        $user = new \ilObjUser($this->user_id);
        return [
            'version' => $this->version,
            'createdAt' => $this->created_at->get(1),
            'userId' => $this->user_id,
            'user' => $user->getPublicName(),
            'url' => $this->url,
            'uuid' => $this->file_uuid->asString()
        ];
    }

}