<?php

namespace srag\Plugins\OnlyOffice\StorageService\Infrastructure\File;

use arException;
use ilDateTime;
use ilDateTimeException;
use ILIAS\DI\Container;
use srag\Plugins\OnlyOffice\StorageService\DTO\FileVersion;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common\UUID;

/**
 * Class FileRepository
 * @package srag\Plugins\OnlyOffice\StorageService\Infrastructure
 * @author  Theodor Truffer <tt@studer-raimann.ch>
 */
class ilDBFileVersionRepository implements FileVersionRepository
{

    /**
     * @param UUID $file_uuid
     * @param int $user_id
     * @param ilDateTime $created_at
     * @return int
     * @throws arException
     */
    public function create(
        UUID $file_uuid,
        int $user_id,
        ilDateTime $created_at,
        string $url,
        int $version = -1
    ) : int {
        $file_version_AR = new FileVersionAR();
        $file_version_AR->setFileUuid($file_uuid);
        if ($version < 0) {
            $file_version_AR->setVersion($this->determineVersion($file_uuid));
        } else {
            $file_version_AR->setVersion($version);
        }
        $file_version_AR->setUserId($user_id);
        $file_version_AR->setCreatedAt($created_at);
        $file_version_AR->setUrl($url);
        $file_version_AR->create();
        return $file_version_AR->getVersion();
    }

    /**
     * @param UUID $file_uuid
     * @return int
     * @throws arException
     */
    protected function determineVersion(UUID $file_uuid) : int
    {
        /** @var FileVersionAR $latest_version */
        $latest_version = FileVersionAR::where(['file_uuid' => $file_uuid->asString()])->orderBy('version',
            'desc')->first();
        return $latest_version ? $latest_version->getVersion() + 1 : FileVersion::FIRST_VERSION;
    }

    /**
     * @param int $object_id
     * @return FileVersion
     */
    public function getByObjectID(int $object_id) : FileVersion
    {
        /** @var FileVersionAR $file_version_ar */
        $file_version_ar = FileVersionAR::where(['id' => $object_id])->first();
        return $this->buildFileVersionFromAR($file_version_ar);
    }

    /**
     * Returns all versions of a file
     * @param UUID $file_uuid
     * @return array
     * @throws arException
     */
    public function getAllVersions(UUID $file_uuid) : array
    {
        /** @var array $all_file_version_ar */
        $all_file_version_ar = FileVersionAR::where(['file_uuid' => $file_uuid->asString()])
                                            ->orderBy('version', 'desc')
                                            ->get();
        $length = count($all_file_version_ar);
        $result = array();
        foreach ($all_file_version_ar as $fileVersionAr) {
            $fileVersion = $this->buildFileVersionFromAR($fileVersionAr);
            array_push($result, $fileVersion);
        }
        return $result;
    }

    public function getLatestVersion(UUID $file_uuid) : FileVersion
    {
        /** @var FileVersionAR $latest_file_version_ar */
        $latest_file_version_ar = FileVersionAR::where(['file_uuid' => $file_uuid->asString()])
                                               ->orderBy('version', 'desc')
                                               ->first();
        return $this->buildFileVersionFromAR($latest_file_version_ar);
    }

    /**
     * @param FileVersionAR $ar
     * @return FileVersion
     */
    protected function buildFileVersionFromAR(FileVersionAR $ar) : FileVersion
    {
        $version = $ar->getVersion();
        $created_at = $ar->getCreatedAt();
        $user_id = $ar->getUserId();
        $url = $ar->getUrl();
        $file_uuid = $ar->getFileUuid();
        return new FileVersion($version, $created_at, $user_id, $url, $file_uuid);
    }

    public function getPreviousVersion(string $uuid, int $version) : FileVersion
    {
        /** @var FileVersionAR $previous_ar */
        $previous_ar = FileVersionAR::where(['file_uuid' => $uuid, 'version' => ($version - 1)])->first();
        return $this->buildFileVersionFromAR($previous_ar);
    }
}