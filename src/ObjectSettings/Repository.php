<?php

namespace ILIAS\Plugin\OnlyOffice\ObjectSettings;

use ILIAS\DI\Container;
use ilOnlyOfficePlugin;
use srag\DIC\OnlyOffice\DICTrait;

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
