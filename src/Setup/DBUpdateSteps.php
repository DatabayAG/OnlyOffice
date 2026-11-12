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
        ];

        foreach ($tables_to_drop as $table_name) {
            $this->db->dropTable($table_name, false);

        }
    }
}
