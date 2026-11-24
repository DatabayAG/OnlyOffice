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

namespace ILIAS\Plugin\OnlyOffice\Setup\Database;

use ilDatabaseUpdateSteps;
use ilDBConstants;
use ilDBInterface;
use ilDBStepExecutionDB;
use ilDBStepReader;

class ExistingInstallationUpdateSteps implements ilDatabaseUpdateSteps
{
    public const string PREFIX = "step_";

    private ilDBInterface $db;

    public function prepare(ilDBInterface $db): void
    {
        $this->db = $db;
    }

    public function install(ilDBInterface $db): void
    {
        $this->prepare($db);

        $execution_log = new ilDBStepExecutionDB($this->db, fn() => new \DateTime());
        $step_reader = new ilDBStepReader();

        $last_started_step = $execution_log->getLastStartedStep(self::class);
        $last_finished_step = $execution_log->getLastFinishedStep(self::class);

        foreach ($step_reader->readStepNumbers(self::class, self::PREFIX) as $step) {
            if ($step <= $last_finished_step) {
                continue;
            }
            $execution_log->started(self::class, $step);
            $method = self::PREFIX . $step;
            $this->$method();
            $execution_log->finished(self::class, $step);
        }
    }

    public function uninstall(ilDBInterface $db): void
    {
        $this->prepare($db);
        $this->db->manipulateF(
            "DELETE FROM il_db_steps WHERE class = %s",
            [ilDBConstants::T_TEXT],
            [self::class]
        );
    }

    public function step_1(): void
    {
        $table_name = "xono_config";

        if (!$this->db->tableExists($table_name)) {
            return;
        }

        $this->db->dropTable($table_name);
    }

    /**
     * Base migration to fix incorrect/malformed ActiveRecord db table structure for ObjectSettings
     */
    public function step_2(): void
    {
        $table_name = "rep_robj_xono_set";

        if (!$this->db->tableExists($table_name)) {
            return;
        }

        if ($this->db->tableColumnExists($table_name, "title")) {
            //- Title can only be a maximum of 255 characters since object_data table has a limit of 255
            $this->db->modifyTableColumn(
                $table_name,
                "title",
                [
                    "type" => ilDBConstants::T_TEXT,
                    "length" => 255,
                    "notnull" => true,
                    "default" => "",
                ]
            );
        }

        if ($this->db->tableColumnExists($table_name, "desc")) {
            //- description can only be a maximum of 128 characters since object_data table has a limit of 128
            //- Can't be null
            $this->db->modifyTableColumn(
                $table_name,
                "desc",
                [
                    "type" => ilDBConstants::T_TEXT,
                    "length" => 128,
                    "notnull" => true,
                    "default" => "",
                ]
            );
        }

        if ($this->db->tableColumnExists($table_name, "limited_period")) {
            //- Can't be null
            $this->db->modifyTableColumn(
                $table_name,
                "limited_period",
                [
                    "type" => ilDBConstants::T_INTEGER,
                    "length" => 1,
                    "notnull" => true,
                    "default" => false,
                ]
            );
        }
    }

    /**
     * Base migration to fix incorrect/malformed ActiveRecord db table structure for FileChange
     */
    public function step_3(): void
    {
        $table_name = "xono_file_change";

        if (!$this->db->tableExists($table_name)) {
            return;
        }

        if ($this->db->tableColumnExists($table_name, "change_id")) {
            //- Increase length from fallback/default to 8
            //- Can't be null
            $this->db->modifyTableColumn(
                $table_name,
                "change_id",
                [
                    "type" => ilDBConstants::T_INTEGER,
                    "length" => 8,
                    "notnull" => true,
                ]
            );
        }

        if ($this->db->tableColumnExists($table_name, "file_uuid")) {
            //- Can't be null
            $this->db->modifyTableColumn(
                $table_name,
                "file_uuid",
                [
                    "type" => ilDBConstants::T_TEXT,
                    "length" => 256,
                    "notnull" => true,
                    "default" => "",
                ]
            );
        }

        if ($this->db->tableColumnExists($table_name, "version")) {
            //- Increase length from fallback/default to 8
            //- Can't be null
            $this->db->modifyTableColumn(
                $table_name,
                "version",
                [
                    "type" => ilDBConstants::T_INTEGER,
                    "length" => 8,
                    "notnull" => true,
                    "default" => 1,
                ]
            );
        }

        if ($this->db->tableColumnExists($table_name, "changes_object_string")) {
            //- Can't be null
            $this->db->modifyTableColumn(
                $table_name,
                "changes_object_string",
                [
                    "type" => ilDBConstants::T_CLOB,
                    "notnull" => true,
                ]
            );
        }

        if ($this->db->tableColumnExists($table_name, "changes_url")) {
            //- Can't be null
            $this->db->modifyTableColumn(
                $table_name,
                "changes_url",
                [
                    "type" => ilDBConstants::T_TEXT,
                    "notnull" => true,
                    "length" => 256,
                    "default" => "",
                ]
            );
        }

        if ($this->db->tableColumnExists($table_name, "server_version")) {
            //- Can't be null
            $this->db->modifyTableColumn(
                $table_name,
                "server_version",
                [
                    "type" => ilDBConstants::T_TEXT,
                    "length" => 64,
                    "notnull" => true,
                    "default" => "6.3.1",
                ]
            );
        }
    }

    /**
     * Base migration to fix incorrect/malformed ActiveRecord db table structure for File
     */
    public function step_4(): void
    {
        $table_name = "xono_file";

        if (!$this->db->tableExists($table_name)) {
            return;
        }

        if ($this->db->tableColumnExists($table_name, "mime_type")) {
            //- Can't be null
            $this->db->modifyTableColumn(
                $table_name,
                "mime_type",
                [
                    "type" => ilDBConstants::T_TEXT,
                    "length" => 256,
                    "notnull" => true,
                    "default" => "",
                ]
            );
        }
    }

    public function step_5(): void
    {
        $table_name = "xono_file_change";
        if (!$this->db->tableExists($table_name)) {
            return;
        }

        if ($this->db->tableColumnExists($table_name, "change_id")) {
            $this->db->renameTableColumn($table_name, "change_id", "id");
        }

        if ($this->db->tableColumnExists($table_name, "changes_url")) {
            $this->db->renameTableColumn($table_name, "changes_url", "url");
        }
    }
}
