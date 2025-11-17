<?php

namespace ILIAS\Plugin\OnlyOffice\ObjectSettings;

use ilOnlyOfficePlugin;
use ilObjOnlyOffice;
use ilObjOnlyOfficeGUI;

final class Factory
{
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

    public function newFormInstance(ilObjOnlyOfficeGUI $parent, ilObjOnlyOffice $object): ObjectSettingsForm
    {
        $form = new ObjectSettingsForm($parent, $object);

        return $form;
    }
}
