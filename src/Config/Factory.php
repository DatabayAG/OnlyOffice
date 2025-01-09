<?php

namespace srag\Plugins\OnlyOffice\Config;

use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use ilOnlyOfficeConfigGUI;
use ilOnlyOfficePlugin;
use srag\ActiveRecordConfig\OnlyOffice\Config\AbstractFactory;

final class Factory extends AbstractFactory
{
    use OnlyOfficeTrait;
    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    protected static ?Factory $instance = null;

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

    public function newFormInstance(ilOnlyOfficeConfigGUI $parent): ConfigFormGUI
    {
        $form = new ConfigFormGUI($parent);
        return $form;
    }
}
