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
use ILIAS\Plugin\OnlyOffice\Model\File;

class FileRepository
{
    public const string TABLE_NAME = "xono_file";

    protected ilDBInterface $db;

    public function __construct(?ilDBInterface $db = null)
    {
        global $DIC;
        $this->db = $db ?: $DIC->database();
    }

    public function read(int $obj_id): ?File
    {
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE obj_id = %s",
            [ilDBConstants::T_INTEGER],
            [$obj_id]
        );

        $row = $this->db->fetchAssoc($result);

        if (!$row) {
            return null;
        }

        return $this->map($row);
    }

    /**
     * @return list<File>
     */
    public function readAll(): array
    {
        $result = $this->db->query("SELECT * FROM " . self::TABLE_NAME);

        $data = [];

        while ($row = $this->db->fetchAssoc($result)) {
            $data[] = $this->map($row);
        }
        return $data;
    }

    public function create(File $file): bool
    {
        return $this->db->insert(
            self::TABLE_NAME,
            [
                "obj_id" => [ilDBConstants::T_INTEGER, $file->getObjId()],
                "uuid" => [ilDBConstants::T_TEXT, $file->getUuid()->toString()],
                "title" => [ilDBConstants::T_TEXT, $file->getTitle()],
                "file_type" => [ilDBConstants::T_TEXT, $file->getFileType()],
                "mime_type" => [ilDBConstants::T_TEXT, $file->getMimeType()],
            ]
        ) === 1;
    }

    public function update(File $file): bool
    {
        return $this->db->update(
            self::TABLE_NAME,
            [
                "uuid" => [ilDBConstants::T_TEXT, $file->getUuid()->toString()],
                "title" => [ilDBConstants::T_TEXT, $file->getTitle()],
                "file_type" => [ilDBConstants::T_TEXT, $file->getFileType()],
                "mime_type" => [ilDBConstants::T_TEXT, $file->getMimeType()],
            ],
            ["obj_id" => [ilDBConstants::T_INTEGER, $file->getObjId()]]
        ) === 1;
    }

    public function store(File $file): bool
    {
        if ($this->exists($file->getObjId())) {
            return $this->update($file);
        }

        return $this->create($file);
    }

    public function delete(File $file): bool
    {
        return $this->deleteById($file->getObjId());
    }

    public function deleteById(int $obj_id): bool
    {
        return $this->db->manipulateF(
            "DELETE FROM " . self::TABLE_NAME . " WHERE obj_id = %s",
            [ilDBConstants::T_INTEGER],
            [$obj_id]
        ) === 1;
    }

    public function exists(int $obj_id): bool
    {
        $result = $this->db->queryF(
            "SELECT EXISTS(SELECT 1 FROM " . self::TABLE_NAME . " WHERE obj_id = %s) AS does_exist",
            [ilDBConstants::T_INTEGER],
            [$obj_id]
        );

        return (bool) $this->db->fetchAssoc($result)["does_exist"];
    }

    private function map(array $row): File
    {
        return new File(
            $row["obj_id"],
            (new UUIDFactory())->fromString($row["uuid"]),
            $row["title"],
            $row["file_type"],
            $row["mime_type"] ?: null,
        );
    }
}
