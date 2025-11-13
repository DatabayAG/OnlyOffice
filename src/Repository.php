<?php

namespace ILIAS\Plugin\OnlyOffice;

use ILIAS\Plugin\OnlyOffice\ObjectSettings\Repository as ObjectSettingsRepository;
use ilOnlyOfficePlugin;


final class Repository
{
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

    public function dropTables(): void
    {
        $this->objectSettings()->dropTables();
    }

    public function installTables(): void
    {
        $this->objectSettings()->installTables();
    }

    public function objectSettings(): ObjectSettingsRepository
    {
        return ObjectSettingsRepository::getInstance();
    }
}
