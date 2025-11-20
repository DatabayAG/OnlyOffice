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

namespace ILIAS\Plugin\OnlyOffice\ObjectSettings;

use ILIAS\DI\Container;
use ilOnlyOfficePlugin;

final class Repository
{
    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    protected static ?Repository $instance = null;
    private Container $dic;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
    }

    public function cloneObjectSettings(ObjectSettings $object_settings): ObjectSettings
    {
        return $object_settings->copy();
    }

    public function deleteObjectSettings(ObjectSettings $object_settings): void
    {
        $object_settings->delete();
    }

    public function dropTables(): void/*:void*/
    {
        $this->dic->database()->dropTable(ObjectSettings::TABLE_NAME, false);
    }

    public function getObjectSettingsById(int $obj_id): ?ObjectSettings
    {
        /**
         * @var ObjectSettings|null $object_settings
         */

        $object_settings = ObjectSettings::where([
            "obj_id" => $obj_id
        ])->first();

        return $object_settings;
    }

    public function installTables(): void
    {
        ObjectSettings::updateDB();
    }

    public function storeObjectSettings(ObjectSettings $object_settings): void
    {
        $object_settings->store();
    }
}
