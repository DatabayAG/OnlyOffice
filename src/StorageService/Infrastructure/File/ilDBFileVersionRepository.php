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

use arException;
use ilDateTime;
use ilDateTimeException;
use ilTimeZone;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\FileVersion;
use ILIAS\Data\UUID\Uuid;

class ilDBFileVersionRepository implements FileVersionRepository
{
    /**
     * @throws ilDateTimeException
     */
    public function create(
        Uuid $file_uuid,
        int $user_id,
        ilDateTime $created_at,
        string $url,
        int $version = -1
    ): int {
        $file_version_AR = new FileVersionAR();
        $file_version_AR->setFileUuid($file_uuid);
        if ($version < 0) {
            $file_version_AR->setVersion($this->determineVersion($file_uuid));
        } else {
            $file_version_AR->setVersion($version);
        }
        $file_version_AR->setUserId($user_id);

        $utc_time = $created_at->get(IL_CAL_DATETIME, 'Y-m-d H:i:s', ilTimeZone::UTC);
        $utc_time_obj = new ilDateTime($utc_time, IL_CAL_DATETIME);

        $file_version_AR->setCreatedAt($utc_time_obj);
        $file_version_AR->setUrl($url);
        $file_version_AR->create();
        return $file_version_AR->getVersion();
    }

    protected function determineVersion(Uuid $file_uuid): int
    {
        /** @var FileVersionAR $latest_version */
        $latest_version = FileVersionAR::where(['file_uuid' => $file_uuid->toString()])->orderBy(
            'version',
            'desc'
        )->first();
        return $latest_version ? $latest_version->getVersion() + 1 : FileVersion::FIRST_VERSION;
    }

    public function getByObjectID(int $object_id): FileVersion
    {
        /** @var FileVersionAR $file_version_ar */
        $file_version_ar = FileVersionAR::where(['id' => $object_id])->first();
        return $this->buildFileVersionFromAR($file_version_ar);
    }

    public function getAllVersions(Uuid $file_uuid): array
    {
        /** @var array $all_file_version_ar */
        $all_file_version_ar = FileVersionAR::where(['file_uuid' => $file_uuid->toString()])
                                            ->orderBy('version', 'desc')
                                            ->get();
        $result = [];
        foreach ($all_file_version_ar as $fileVersionAr) {
            $fileVersion = $this->buildFileVersionFromAR($fileVersionAr);
            $result[] = $fileVersion;
        }
        return $result;
    }

    public function getLatestVersion(Uuid $file_uuid): ?FileVersion
    {
        /** @var FileVersionAR $latest_file_version_ar */
        $latest_file_version_ar = FileVersionAR::where(['file_uuid' => $file_uuid->toString()])
                                               ->orderBy('version', 'desc')
                                               ->first();
        if (is_null($latest_file_version_ar)) {
            return null;
        }
        return $this->buildFileVersionFromAR($latest_file_version_ar);
    }

    protected function buildFileVersionFromAR(FileVersionAR $ar): FileVersion
    {
        $version = $ar->getVersion();
        $created_at = $ar->getCreatedAt();
        $user_id = $ar->getUserId();
        $url = $ar->getUrl();
        $file_uuid = $ar->getFileUuid();
        return new FileVersion($version, $created_at, $user_id, $url, $file_uuid);
    }

    public function getPreviousVersion(string $uuid, int $version): FileVersion
    {
        /** @var FileVersionAR $previous_ar */
        $previous_ar = FileVersionAR::where(['file_uuid' => $uuid, 'version' => ($version - 1)])->first();
        return $this->buildFileVersionFromAR($previous_ar);
    }
}
