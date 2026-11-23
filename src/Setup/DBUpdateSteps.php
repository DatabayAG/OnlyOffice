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

namespace ILIAS\Plugin\OnlyOffice\Setup;

use ilAccessRBACOperationDeletedObjective;
use ilDatabaseUpdateSteps;
use ilDBConstants;
use ilDBInterface;
use ilDBStepExecutionDB;
use ilDBStepReader;
use ilDBUpdateNewObjectType;
use ILIAS\Plugin\OnlyOffice\Repository\FileChangeRepository;
use ILIAS\Plugin\OnlyOffice\Repository\FileRepository;
use ILIAS\Plugin\OnlyOffice\Repository\FileTemplateRepository;
use ILIAS\Plugin\OnlyOffice\Repository\FileVersionRepository;
use ILIAS\Plugin\OnlyOffice\Repository\ObjectSettingsRepository;
use ILIAS\Setup\ArrayEnvironment;
use ILIAS\Setup\Environment;
use ilOnlyOfficePlugin;
use ilRbacReview;

class DBUpdateSteps implements ilDatabaseUpdateSteps
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

        // Add new object type, ILIAS only adds this automatically if the plugin is ACTIVATED,
        // if the plugin is uninstalled before it was ever activated this will result in an exception.
        // => SetupAgent should usually do these next steps using NewTypeAddedObjective & ilAccessCustomRBACOperationAddedObjective objectives,
        //    however SetupAgent objectives are only executed when installed/updated through CLI.

        require_once dirname(__DIR__, 10) . "/components/ILIAS/Migration/DBUpdate_3560/classes/class.ilDBUpdateNewObjectType.php";

        $xono_type_id = ilDBUpdateNewObjectType::addNewType(ilOnlyOfficePlugin::ID, 'Plugin OnlyOffice');

        $offering_admin = ilDBUpdateNewObjectType::addCustomRBACOperation('rep_robj_xono_perm_editFile', 'editFile', 'object', 2010);
        if ($offering_admin) {
            ilDBUpdateNewObjectType::addRBACOperation($xono_type_id, $offering_admin);
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

        $tables_to_drop = [
            ObjectSettingsRepository::TABLE_NAME,
            FileRepository::TABLE_NAME,
            FileVersionRepository::TABLE_NAME,
            FileChangeRepository::TABLE_NAME,
            FileTemplateRepository::TABLE_NAME
        ];

        foreach ($tables_to_drop as $table_name) {
            $this->db->dropTable($table_name, false);
        }

        $operation = "rep_robj_xono_perm_editFile";
        $op_id = ilRbacReview::_getCustomRBACOperationId($operation);

        if ($op_id !== null) {
            $env = new ArrayEnvironment([
                Environment::RESOURCE_DATABASE => $this->db
            ]);

            $objective = new ilAccessRBACOperationDeletedObjective(ilOnlyOfficePlugin::ID, $op_id);
            if ($objective->isApplicable($env)) {
                $objective->achieve($env);
            }

            //ilAccessRBACOperationDeletedObjective doesn't delete the entry added to rbac_operations
            $this->db->manipulateF(
                "DELETE FROM rbac_operations WHERE operation = %s AND ops_id = %s",
                [ilDBConstants::T_TEXT, ilDBConstants::T_INTEGER],
                [$operation, $op_id]
            );
        }
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

    public function step_6(): void
    {
        $table_name = "rep_robj_xono_set";

        if ($this->db->tableExists($table_name)) {
            return;
        }

        $this->db->createTable($table_name, [
            "obj_id" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 8,
                "notnull" => true,
            ],
            "title" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 255,
                "notnull" => true,
                "default" => "",
            ],
            "desc" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 128,
                "notnull" => true,
                "default" => ""
            ],
            "open_setting" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 10,
                "notnull" => true,
            ],
            "is_online" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 1,
                "notnull" => true,
                "default" => false,
            ],
            "limited_period" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 1,
                "notnull" => true,
                "default" => false,
            ],
            "allow_edit" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 1,
                "notnull" => true,
                "default" => false,
            ],
            "start_time" => [
                "type" => ilDBConstants::T_TIMESTAMP,
                "notnull" => false,
            ],
            "end_time" => [
                "type" => ilDBConstants::T_TIMESTAMP,
                "notnull" => false,
            ],
        ]);
        $this->db->addPrimaryKey($table_name, ["obj_id"]);
    }

    public function step_7(): void
    {
        $table_name = "xono_file_version";

        if ($this->db->tableExists($table_name)) {
            return;
        }

        $this->db->createTable($table_name, [
            "id" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 8,
                "notnull" => true,
            ],
            "file_uuid" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 256,
                "notnull" => true,
            ],
            "user_id" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 8,
                "notnull" => true,
            ],
            "created_at" => [
                "type" => ilDBConstants::T_TIMESTAMP,
                "notnull" => true,
            ],
            "version" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 8,
                "notnull" => true,
            ],
        ]);
        $this->db->addPrimaryKey($table_name, ["id"]);
        $this->db->createSequence($table_name);
    }

    public function step_8(): void
    {
        $table_name = "xono_file_change";

        if ($this->db->tableExists($table_name)) {
            return;
        }

        $this->db->createTable($table_name, [
            "id" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 8,
                "notnull" => true,
            ],
            "file_uuid" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 256,
                "notnull" => true,
                "default" => "",
            ],
            "version" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 8,
                "notnull" => true,
                "default" => 1,
            ],
            "changes_object_string" => [
                "type" => ilDBConstants::T_CLOB,
                "notnull" => true,
            ],
            "server_version" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 64,
                "notnull" => true,
                "default" => "6.3.1",
            ],
        ]);
        $this->db->addPrimaryKey($table_name, ["id"]);
        $this->db->createSequence($table_name);
    }

    public function step_9(): void
    {
        $table_name = "xono_file";

        if ($this->db->tableExists($table_name)) {
            return;
        }

        $this->db->createTable($table_name, [
            "obj_id" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 8,
                "notnull" => true,
            ],
            "uuid" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 256,
                "notnull" => true,
            ],
            "title" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 256,
                "notnull" => true,
            ],
            "file_type" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 256,
                "notnull" => true,
            ],
            "mime_type" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 256,
                "notnull" => true,
                "default" => "",
            ],
        ]);
        $this->db->addPrimaryKey($table_name, ["obj_id"]);
    }

    public function step_10(): void
    {
        $file_version_table_name = "xono_file_version";
        $file_change_table_name = "xono_file_change";
        $new_column_name = "rid";

        if (
            $this->db->tableExists($file_version_table_name)
            && !$this->db->tableColumnExists($file_version_table_name, $new_column_name)
        ) {
            $this->db->addTableColumn($file_version_table_name, $new_column_name, [
                "type" => ilDBConstants::T_CLOB,
                "notnull" => false
            ]);
        }

        if (
            $this->db->tableExists($file_change_table_name)
            && !$this->db->tableColumnExists($file_change_table_name, $new_column_name)
        ) {
            $this->db->addTableColumn($file_change_table_name, $new_column_name, [
                "type" => ilDBConstants::T_CLOB,
                "notnull" => false
            ]);
        }
    }

    public function step_11(): void
    {
        $table_name = "xono_file_template";
        if ($this->db->tableExists($table_name)) {
            return;
        }

        $this->db->createTable($table_name, [
            "id" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 8,
                "notnull" => true,
            ],
            "title" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 255,
                "notnull" => true,
            ],
            "description" => [
                "type" => ilDBConstants::T_CLOB,
                "length" => 255,
                "notnull" => true,
            ],
            "type" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 16,
                "notnull" => true,
            ],
            "extension" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 16,
                "notnull" => true,
            ],
            "rid" => [
                "type" => ilDBConstants::T_CLOB,
                "notnull" => true
            ]
        ]);

        $this->db->addPrimaryKey($table_name, ["id"]);
        $this->db->createSequence($table_name);
    }
}
