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

namespace ILIAS\Plugin\OnlyOffice\Setup\Migration;

use ilDatabaseUpdateStepsExecutedObjective;
use ilDBConstants;
use ilDBStatement;
use ILIAS\Plugin\OnlyOffice\Setup\Database\UpdateSteps;
use ILIAS\Plugin\OnlyOffice\Stakeholder\FileStakeholder;
use ILIAS\Setup\CLI\IOWrapper;
use ILIAS\Setup\Environment;
use ILIAS\Setup\Migration;
use ilResourceStorageMigrationHelper;
use ReflectionClass;

class FileIrssMigration implements Migration
{
    public const int NUMBER_OF_STEPS = 5;

    public const string FILE_VERSION_TABLE_NAME = "xono_file_version";
    public const string FILE_CHANGE_TABLE_NAME = "xono_file_change";

    private ilResourceStorageMigrationHelper $helper;
    private ?IOWrapper $io = null;
    private ilDBStatement $file_version_prepared_statement;
    private ilDBStatement $file_change_prepared_statement;

    public function getLabel(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    public function getDefaultAmountOfStepsPerRun(): int
    {
        return self::NUMBER_OF_STEPS;
    }

    public function getPreconditions(Environment $environment): array
    {
        return [
            ...ilResourceStorageMigrationHelper::getPreconditions(),
            new ilDatabaseUpdateStepsExecutedObjective(new UpdateSteps())
        ];
    }

    public function prepare(Environment $environment): void
    {
        $this->helper = new ilResourceStorageMigrationHelper(
            new FileStakeholder(),
            $environment
        );

        $io = $environment->getResource(Environment::RESOURCE_ADMIN_INTERACTION);
        if ($io instanceof IOWrapper) {
            $this->io = $io;
        }

        $this->file_version_prepared_statement = $this->helper->getDatabase()->prepareManip(
            "UPDATE " . self::FILE_VERSION_TABLE_NAME . " SET url = '', rid = ? WHERE id = ?",
            [
                ilDBConstants::T_CLOB,
                ilDBConstants::T_INTEGER
            ]
        );

        $this->file_change_prepared_statement = $this->helper->getDatabase()->prepareManip(
            "UPDATE " . self::FILE_CHANGE_TABLE_NAME . " SET url = '', rid = ? WHERE id = ?",
            [
                ilDBConstants::T_CLOB,
                ilDBConstants::T_INTEGER
            ]
        );
    }

    public function step(Environment $environment): void
    {
        $migrated_url_rid_cache = [];

        $this->migrateTable(
            self::FILE_VERSION_TABLE_NAME,
            $this->file_version_prepared_statement,
            $migrated_url_rid_cache
        );

        $this->migrateTable(
            self::FILE_CHANGE_TABLE_NAME,
            $this->file_change_prepared_statement,
            $migrated_url_rid_cache
        );
    }

    public function getRemainingAmountOfSteps(): int
    {
        $db = $this->helper->getDatabase();
        $count = 0;

        foreach ([self::FILE_VERSION_TABLE_NAME, self::FILE_CHANGE_TABLE_NAME] as $table_name) {
            $table_count = $this->getRemainingAmountOfStepsForTable($table_name);
            $count += $table_count;

            if ($table_count === 0
                && $db->tableExists($table_name)
                && $db->tableColumnExists($table_name, "url")
            ) {
                $db->dropTableColumn($table_name, "url");
            }
        }

        return $count;
    }

    private function getRemainingAmountOfStepsForTable(string $table_name): int
    {
        $db = $this->helper->getDatabase();

        if (!$db->tableExists($table_name) || !$db->tableColumnExists($table_name, "url")) {
            return 0;
        }

        return (int) $db->fetchAssoc($db->query(
            "SELECT COUNT(id) count FROM $table_name WHERE rid IS NULL AND url != ''"
        ))["count"];
    }

    /**
     * @param array<string, string> $migrated_url_rid_cache
     */
    public function migrateTable(
        string $table_name,
        ilDBStatement $prepared_statement,
        array &$migrated_url_rid_cache
    ): void {
        $db = $this->helper->getDatabase();

        $result = $db->query($this->buildSelectQuery($table_name));

        /** @var array{id: string, url: string} $row */
        while ($row = $db->fetchAssoc($result)) {
            $row_id = (int) $row["id"];
            $file_url = $row["url"];

            if (isset($migrated_url_rid_cache[$file_url])) {
                $rid = $migrated_url_rid_cache[$file_url];
            } else {
                $sanitized_url = ltrim($file_url);
                if (str_starts_with($sanitized_url, "/")) {
                    $sanitized_url = substr($sanitized_url, 1);
                }

                $full_path = ILIAS_ABSOLUTE_PATH . "/" . ILIAS_WEB_DIR . "/" . CLIENT_ID . "/" . $sanitized_url;

                $access_error = false;

                $file_exists = file_exists($full_path);

                if ($file_exists && !is_readable($full_path)) {
                    $this->error("Missing permission to read file '$full_path'. Please check permissions before re-running migration");
                    $access_error = true;
                }

                if ($file_exists && !is_writable($full_path)) {
                    $this->error("Missing permission to write file '$full_path'. Please check permissions before re-running migration");
                    $access_error = true;
                }

                if ($access_error) {
                    continue;
                }

                if ($file_exists) {
                    $rid = $this->helper->movePathToStorage(
                        $full_path,
                        $this->helper->getStakeholder()->getOwnerOfNewResources(),
                        null,
                        null,
                        true
                    );

                    if (!$rid) {
                        $this->error(sprintf(
                            "Error occurred trying to migrate file '%s'. Original file was not deleted!",
                            $full_path
                        ));
                        continue;
                    }

                    if (!unlink($full_path)) {
                        $this->error(sprintf(
                            "Error occurred trying to delete original file '%s'. Manual deletion required!",
                            $full_path
                        ));
                    }

                    $this->inform(sprintf(
                        "Successfully migrated file '%s'. New rid: '%s'",
                        $full_path,
                        $rid->serialize()
                    ));
                } else {
                    $this->error("File '$full_path' does not exist. File will not be migrated. Resource identification falling back to 'null'");
                    $rid = null;
                }

                $migrated_url_rid_cache[$file_url] = $rid;
            }

            $rid = $rid ? $rid->serialize() : null;

            $db->execute($prepared_statement, [
                $rid,
                $row_id
            ]);
        }

        //Only drop table column if migration was completely finished
        if ($this->getRemainingAmountOfStepsForTable($table_name) === 0) {
            $db->dropTableColumn($table_name, "url");
        }
    }

    public function buildSelectQuery(string $table_name): string
    {
        $select_fields = [
            "id",
            "url"
        ];

        return "SELECT "
            . implode(", ", $select_fields)
            . " FROM $table_name WHERE rid IS NULL AND url != ''";
    }

    private function inform(string $text, bool $force = false): void
    {
        if ($this->io === null || (!$force && !$this->io->isVerbose())) {
            return;
        }

        $this->io->inform($text);
    }

    private function error(string $text): void
    {
        if ($this->io === null) {
            return;
        }

        $this->io->error($text);
    }
}
