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
use ILIAS\Data\UUID\Uuid;
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Model\FileTemplate;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;

class FileTemplateRepository
{
    public const string TABLE_NAME = "xono_file_template";

    protected ilDBInterface $db;

    public function __construct(?ilDBInterface $db = null)
    {
        global $DIC;
        $this->db = $db ?: $DIC->database();
    }

    public function read(int $id): ?FileTemplate
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

    /**
     * @param list<FileCreationType> $types
     * @return list<FileTemplate>
     */
    public function readAll(array $types = []): array
    {
        $where_clause = "";
        if ($types !== []) {
            $where_clause = " WHERE " . $this->db->in("type", array_map(static function (FileCreationType $type): string {
                return $type->value;
            }, $types), false, ilDBConstants::T_TEXT);
        }
        $result = $this->db->query("SELECT * FROM " . self::TABLE_NAME . $where_clause);

        $data = [];

        while ($row = $this->db->fetchAssoc($result)) {
            $data[] = $this->map($row);
        }
        return $data;
    }

    public function create(FileTemplate $file_template): bool
    {
        $file_template->setId($this->db->nextId(self::TABLE_NAME));

        return $this->db->insert(
                self::TABLE_NAME,
                [
                    "id" => [ilDBConstants::T_INTEGER, $file_template->getId()],
                    "title" => [ilDBConstants::T_TEXT, $file_template->getTitle()],
                    "description" => [ilDBConstants::T_CLOB, $file_template->getDescription()],
                    "type" => [ilDBConstants::T_TEXT, $file_template->getType()->value],
                    "extension" => [ilDBConstants::T_TEXT, $file_template->getExtension()],
                    "rid" => [ilDBConstants::T_CLOB, $file_template->getRid()->serialize()],
                ]
            ) === 1;
    }

    public function update(FileTemplate $file_template): bool
    {
        return $this->db->update(
                self::TABLE_NAME,
                [
                    "title" => [ilDBConstants::T_TEXT, $file_template->getTitle()],
                    "description" => [ilDBConstants::T_CLOB, $file_template->getDescription()],
                    "type" => [ilDBConstants::T_TEXT, $file_template->getType()->value],
                    "extension" => [ilDBConstants::T_TEXT, $file_template->getExtension()],
                    "rid" => [ilDBConstants::T_CLOB, $file_template->getRid()->serialize()],
                ],
                ["id" => [ilDBConstants::T_INTEGER, $file_template->getId()]]
            ) === 1;
    }

    public function store(FileTemplate $file_template): bool
    {
        if ($this->exists($file_template->getId())) {
            return $this->update($file_template);
        }

        return $this->create($file_template);
    }

    public function delete(FileTemplate $file_template): bool
    {
        return $this->deleteById($file_template->getId());
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

    private function map(array $row): FileTemplate
    {
        return (new FileTemplate(
            $row["title"],
            $row["description"],
            $row["extension"],
            FileCreationType::tryFrom($row["type"]) ?? FileCreationType::NONE,
            new ResourceIdentification($row["rid"]),
        ))->setId((int) $row["id"]);
    }
}
