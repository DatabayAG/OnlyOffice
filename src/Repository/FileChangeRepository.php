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

use ilDBConstants;
use ilDBInterface;
use ILIAS\Data\UUID\Factory as UUIDFactory;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Plugin\OnlyOffice\Model\FileChange;

class FileChangeRepository
{
    public const string TABLE_NAME = "xono_file_change";

    protected ilDBInterface $db;

    public function __construct(?ilDBInterface $db = null)
    {
        global $DIC;
        $this->db = $db ?: $DIC->database();
    }

    public function read(int $obj_id): ?FileChange
    {
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE id = %s",
            [ilDBConstants::T_INTEGER],
            [$obj_id]
        );

        $row = $this->db->fetchAssoc($result);

        if (!$row) {
            return null;
        }

        return $this->map($row);
    }

    public function readLatestByUuid(Uuid $uuid): ?FileChange
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

    /**
     * @return array<int, FileChange>
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
            $file_change = $this->map($row);
            $data[$file_change->getVersion()] = $file_change;
        }
        return $data;
    }

    public function readByUuid(Uuid $uuid, int $version): ?FileChange
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

    public function create(FileChange $file_change): bool
    {
        $file_change->setId($this->db->nextId(self::TABLE_NAME));

        if (!$file_change->getServerVersion()) {
            $file_change->setServerVersion(FileChange::DEFAULT_SERVER_VERSION);
        }

        return $this->db->insert(
            self::TABLE_NAME,
            [
                    "id" => [ilDBConstants::T_INTEGER, $file_change->getId()],
                    "file_uuid" => [ilDBConstants::T_TEXT, $file_change->getFileUuid()->toString()],
                    "version" => [ilDBConstants::T_INTEGER, $file_change->getVersion()],
                    "changes_object_string" => [ilDBConstants::T_CLOB, $file_change->getChangesObjectString()],
                    "url" => [ilDBConstants::T_TEXT, $file_change->getUrl()],
                    "server_version" => [ilDBConstants::T_TEXT, $file_change->getServerVersion()],
                ]
        ) === 1;
    }

    public function update(FileChange $file_change): bool
    {
        return $this->db->update(
            self::TABLE_NAME,
            [
                    "id" => [ilDBConstants::T_INTEGER, $file_change->getId()],
                    "file_uuid" => [ilDBConstants::T_TEXT, $file_change->getFileUuid()->toString()],
                    "version" => [ilDBConstants::T_INTEGER, $file_change->getVersion()],
                    "changes_object_string" => [ilDBConstants::T_CLOB, $file_change->getChangesObjectString()],
                    "url" => [ilDBConstants::T_TEXT, $file_change->getUrl()],
                    "server_version" => [ilDBConstants::T_TEXT, $file_change->getServerVersion()],
                ],
            ["id" => [ilDBConstants::T_INTEGER, $file_change->getId()]]
        ) === 1;
    }

    public function store(FileChange $file_change): bool
    {
        if ($this->exists($file_change->getId())) {
            return $this->update($file_change);
        }

        return $this->create($file_change);
    }

    public function delete(FileChange $file_change): bool
    {
        return $this->deleteById($file_change->getId());
    }

    public function deleteById(int $obj_id): bool
    {
        return $this->db->manipulateF(
            "DELETE FROM " . self::TABLE_NAME . " WHERE id = %s",
            [ilDBConstants::T_INTEGER],
            [$obj_id]
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

    public function exists(int $obj_id): bool
    {
        $result = $this->db->queryF(
            "SELECT EXISTS(SELECT 1 FROM " . self::TABLE_NAME . " WHERE id = %s) AS does_exist",
            [ilDBConstants::T_INTEGER],
            [$obj_id]
        );

        return (bool) $this->db->fetchAssoc($result)["does_exist"];
    }

    private function map(array $row): FileChange
    {
        return (new FileChange(
            (new UUIDFactory())->fromString($row["file_uuid"]),
            (int) $row["version"],
            $row["changes_object_string"],
            $row["url"],
            $row["server_version"]
        ))->setId((int) $row["id"]);
    }
}
