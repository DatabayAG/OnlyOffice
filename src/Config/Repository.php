<?php

namespace srag\Plugins\OnlyOffice\Config;

use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use ilOnlyOfficePlugin;
use srag\ActiveRecordConfig\OnlyOffice\Config\AbstractFactory;
use srag\ActiveRecordConfig\OnlyOffice\Config\AbstractRepository;
use srag\ActiveRecordConfig\OnlyOffice\Config\Config;

final class Repository extends AbstractRepository
{
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

    protected function __construct()
    {
        parent::__construct();
    }

    public function factory(): AbstractFactory
    {
        return Factory::getInstance();
    }

    protected function getTableName(): string
    {
        return ilOnlyOfficePlugin::PLUGIN_ID . "_config";
    }

    protected function getFields(): array
    {
        return [
            ConfigFormGUI::KEY_ONLYOFFICE_URL => Config::TYPE_STRING,
            ConfigFormGUI::KEY_ONLYOFFICE_SECRET => Config::TYPE_STRING,
            ConfigFormGUI::KEY_NUM_VERSIONS => Config::TYPE_INTEGER
        ];
    }
}
