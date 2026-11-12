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

use ilDatabaseUpdateSteps;
use ilDBConstants;
use ilDBInterface;
use ilDBStepExecutionDB;
use ilDBStepReader;
use ILIAS\Plugin\OnlyOffice\Repository\ObjectSettingsRepository;

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
            ObjectSettingsRepository::TABLE_NAME
        ];

        foreach ($tables_to_drop as $table_name) {
            $this->db->dropTable($table_name, false);

        }
    }

    public function step_1(): void
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
                "notnull" => true,
            ],
            "desc" => [
                "type" => ilDBConstants::T_TEXT,
                "notnull" => true,
            ],
            "open_setting" => [
                "type" => ilDBConstants::T_TEXT,
                "length" => 10,
                "notnull" => true,
            ],
            "is_online" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 1,
                "default" => false,
                "notnull" => true,
            ],
            "limited_period" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 1,
                "default" => false,
                "notnull" => true,
            ],
            "allow_edit" => [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 1,
                "default" => false,
                "notnull" => true,
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
}
