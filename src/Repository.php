<?php

namespace srag\Plugins\OnlyOffice;

use srag\Plugins\OnlyOffice\Config\Repository as ConfigRepository;
use srag\Plugins\OnlyOffice\ObjectSettings\Repository as ObjectSettingsRepository;
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

    public function config(): ConfigRepository
    {
        return ConfigRepository::getInstance();
    }

    public function dropTables(): void
    {
        $this->config()->dropTables();
        $this->objectSettings()->dropTables();
    }

    public function installTables(): void
    {
        $this->config()->installTables();
        $this->objectSettings()->installTables();
    }

    public function objectSettings(): ObjectSettingsRepository
    {
        return ObjectSettingsRepository::getInstance();
    }
}
