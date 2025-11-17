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
