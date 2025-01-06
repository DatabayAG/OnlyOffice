<?php

namespace srag\Plugins\OnlyOffice\ObjectSettings;

use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use ilOnlyOfficePlugin;
use ilObjOnlyOffice;
use ilObjOnlyOfficeGUI;
use srag\DIC\OnlyOffice\DICTrait;

final class Factory
{
    use DICTrait;
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

    private function __construct()
    {

    }

    public function newInstance(): ObjectSettings
    {
        $object_settings = new ObjectSettings();

        return $object_settings;
    }

    public function newFormInstance(ilObjOnlyOfficeGUI $parent, ilObjOnlyOffice $object): ObjectSettingsFormGUI
    {
        $form = new ObjectSettingsFormGUI($parent, $object);

        return $form;
    }
}
