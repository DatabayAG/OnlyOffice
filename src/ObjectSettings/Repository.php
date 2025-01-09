<?php

namespace srag\Plugins\OnlyOffice\ObjectSettings;

use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use ilOnlyOfficePlugin;
use srag\DIC\OnlyOffice\DICTrait;

final class Repository
{
    use DICTrait;
    use OnlyOfficeTrait;

    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    protected static ?Repository $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {

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
        self::dic()->database()->dropTable(ObjectSettings::TABLE_NAME, false);
    }

    public function factory(): Factory
    {
        return Factory::getInstance();
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
