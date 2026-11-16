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

namespace ILIAS\Plugin\OnlyOffice\Repository;

use ilDateTime;
use ilDBConstants;
use ilDBInterface;
use ILIAS\Data\UUID\Factory as UUIDFactory;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Plugin\OnlyOffice\Model\FileVersion;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;

class FileVersionRepository
{
    public const string TABLE_NAME = "xono_file_version";

    protected ilDBInterface $db;

    public function __construct(?ilDBInterface $db = null)
    {
        global $DIC;
        $this->db = $db ?: $DIC->database();
    }

    public function read(int $id): ?FileVersion
    {
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE id = %s",
            [ilDBConstants::T_INTEGER],
            [$id]
        );

        $row = $this->db->fetchAssoc($result);

        if (!$row) {
            return null;
        }

        return $this->map($row);
    }

    public function readLatestByUuid(Uuid $uuid): ?FileVersion
    {
        $this->db->setLimit(1);
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE file_uuid = %s ORDER BY version DESC",
            [ilDBConstants::T_TEXT],
            [$uuid->toString()]
        );

        $row = $this->db->fetchAssoc($result);

        if (!$row) {
            return null;
        }

        return $this->map($row);
    }

    public function readByUuid(Uuid $uuid, int $version): ?FileVersion
    {
        $this->db->setLimit(1);
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE file_uuid = %s AND version = %s",
            [ilDBConstants::T_TEXT, ilDBConstants::T_INTEGER],
            [$uuid->toString(), $version]
        );

        $row = $this->db->fetchAssoc($result);

        if (!$row) {
            return null;
        }

        return $this->map($row);
    }

    /**
     * @return list<FileVersion>
     */
    public function readAllByUuid(Uuid $uuid): array
    {
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE file_uuid = %s ORDER BY version DESC",
            [ilDBConstants::T_TEXT],
            [$uuid->toString()]
        );

        $data = [];

        while ($row = $this->db->fetchAssoc($result)) {
            $data[] = $this->map($row);
        }
        return $data;
    }

    public function create(FileVersion $file_version): bool
    {
        $file_version->setId($this->db->nextId(self::TABLE_NAME));

        if ($file_version->getVersion() < 0) {
            $latest_file_version = $this->readLatestByUuid($file_version->getFileUuid());
            $file_version->setVersion($latest_file_version ? ($latest_file_version->getVersion() + 1) : FileVersion::FIRST_VERSION);
        }

        return $this->db->insert(
            self::TABLE_NAME,
            [
                "id" => [ilDBConstants::T_INTEGER, $file_version->getId()],
                "file_uuid" => [ilDBConstants::T_TEXT, $file_version->getFileUuid()],
                "user_id" => [ilDBConstants::T_INTEGER, $file_version->getUserId()],
                "created_at" => [ilDBConstants::T_TIMESTAMP, $file_version->getCreatedAt()->get(IL_CAL_DATETIME)],
                "rid" => [ilDBConstants::T_TEXT, $file_version->getRid()?->serialize()],
                "version" => [ilDBConstants::T_INTEGER, $file_version->getVersion()],
            ]
        ) === 1;
    }

    public function update(FileVersion $file_version): bool
    {
        return $this->db->update(
            self::TABLE_NAME,
            [
                "file_uuid" => [ilDBConstants::T_TEXT, $file_version->getFileUuid()],
                "user_id" => [ilDBConstants::T_INTEGER, $file_version->getUserId()],
                "created_at" => [ilDBConstants::T_TIMESTAMP, $file_version->getCreatedAt()->get(IL_CAL_DATETIME)],
                "rid" => [ilDBConstants::T_TEXT, $file_version->getRid()?->serialize()],
                "version" => [ilDBConstants::T_INTEGER, $file_version->getVersion()],
            ],
            ["id" => [ilDBConstants::T_INTEGER, $file_version->getId()]]
        ) === 1;
    }

    public function store(FileVersion $file_version): bool
    {
        if ($this->exists($file_version->getId())) {
            return $this->update($file_version);
        }

        return $this->create($file_version);
    }

    public function delete(FileVersion $file_version): bool
    {
        return $this->deleteById($file_version->getId());
    }

    public function deleteById(int $id): bool
    {
        return $this->db->manipulateF(
            "DELETE FROM " . self::TABLE_NAME . " WHERE id = %s",
            [ilDBConstants::T_INTEGER],
            [$id]
        ) === 1;
    }

    public function deleteByFileUuid(Uuid $uuid): bool
    {
        return $this->db->manipulateF(
            "DELETE FROM " . self::TABLE_NAME . " WHERE file_uuid = %s",
            [ilDBConstants::T_TEXT],
            [$uuid->toString()]
        ) === 1;
    }

    public function exists(int $id): bool
    {
        $result = $this->db->queryF(
            "SELECT EXISTS(SELECT 1 FROM " . self::TABLE_NAME . " WHERE id = %s) AS does_exist",
            [ilDBConstants::T_INTEGER],
            [$id]
        );

        return (bool) $this->db->fetchAssoc($result)["does_exist"];
    }

    private function map(array $row): FileVersion
    {
        return (new FileVersion(
            (new UUIDFactory())->fromString($row["file_uuid"]),
            (int) $row["user_id"],
            new ilDateTime($row["created_at"], IL_CAL_DATETIME),
            $row["rid"] ? new ResourceIdentification($row["rid"]) : null,
            (int) $row["version"]
        ))->setId((int) $row["id"]);
    }
}
