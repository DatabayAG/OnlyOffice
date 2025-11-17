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

namespace ILIAS\Plugin\OnlyOffice\Form\Property;

use ILIAS\Plugin\OnlyOffice\Enum\OpenSetting;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ilLanguage;
use ilObjectProperty;
use ilOnlyOfficePlugin;

class OpenSettingProperty implements ilObjectProperty
{
    private ilOnlyOfficePlugin $plugin;

    public function __construct(
        private readonly OpenSetting $openMode = OpenSetting::EDITOR
    ) {
        $this->plugin = ilOnlyOfficePlugin::getInstance();
    }

    public function toForm(
        ilLanguage $language,
        FieldFactory $field_factory,
        Refinery $refinery
    ): FormInput {
        $trafo = $refinery->custom()->transformation(
            function ($vs): OpenSetting {
                return OpenSetting::from($vs);
            }
        );

        return $field_factory->radio($this->plugin->txt("form_open_setting"))
            ->withOption(OpenSetting::EDITOR->value, $this->plugin->txt("settings_open_setting_editor"))
            ->withOption(OpenSetting::ILIAS->value, $this->plugin->txt("settings_open_setting_ilias"))
            ->withOption(OpenSetting::DOWNLOAD->value, $this->plugin->txt("settings_open_setting_download"))
            ->withRequired(true)
            ->withAdditionalTransformation($trafo)
            ->withValue($this->getOpenMode()->value);
    }

    public function getOpenMode(): OpenSetting
    {
        return $this->openMode;
    }

}
