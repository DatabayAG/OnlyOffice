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

namespace ILIAS\Plugin\OnlyOffice\Form;

use DateTimeImmutable;
use ilCtrlInterface;
use ILIAS\DI\Container;
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Form\Property\AllowEditProperty;
use ILIAS\Plugin\OnlyOffice\Form\Property\FileSettingProperty;
use ILIAS\Plugin\OnlyOffice\Form\Property\OpenSettingProperty;
use ILIAS\Plugin\OnlyOffice\Model\ObjectSettings;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;
use ilLanguage;
use ilObject;
use ilObjOnlyOfficeGUI;
use ilOnlyOfficePlugin;
use ILIAS\UI\Factory;

class ObjectSettingsForm
{
    public const string OPTION_SETTING_CREATE = "create_file";
    public const string OPTION_SETTING_UPLOAD = "upload_file";
    public const string OPTION_SETTING_TEMPLATE = "template_file";

    public const string POST_VAR_FILE = 'upload_files';
    public const string POST_VAR_FILE_SETTING = 'file_setting';
    public const string POST_VAR_FILE_CREATION_SETTING = 'file_creation_setting';
    public const string POST_VAR_FILE_TEMPLATE_SETTING = 'file_template_setting';
    public const string POST_VAR_OPEN_SETTING = 'open_setting';
    public const string POST_VAR_ONLINE = 'online';
    public const string POST_VAR_EDIT = 'allow_edit';
    public const string POST_VAR_EDIT_LIMITED = 'allow_edit_limited';
    public const string POST_VAR_EDIT_LIMITED_START = 'start_time';
    public const string POST_VAR_EDIT_LIMITED_END = 'end_time';
    public const string POST_VAR_CREATE = 'createFrom';

    private readonly ilOnlyOfficePlugin $plugin;
    private readonly Container $dic;
    private readonly Factory $ui_factory;
    private readonly ilCtrlInterface $ctrl;
    private readonly ilLanguage $lng;
    private readonly \ILIAS\Refinery\Factory $refinery;
    private readonly StandardForm $form;

    public function __construct(?ObjectSettings $object_settings = null, bool $new_object = false)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->ui_factory = $this->dic->ui()->factory();
        $this->plugin = ilOnlyOfficePlugin::getInstance();
        $this->ctrl = $this->dic->ctrl();
        $this->lng = $this->dic->language();
        $this->refinery = $this->dic->refinery();

        $this->form = $this->buildForm($object_settings, $new_object);
    }

    public function getForm(): StandardForm
    {
        return $this->form;
    }

    private function buildForm(?ObjectSettings $object_settings, bool $new_object): StandardForm
    {
        $storage_service = $this->plugin->getStorageService();
        $templates = [];

        foreach (FileCreationType::cases() as $type) {
            if ($type === FileCreationType::NONE) {
                continue;
            }
            $templates = [...$templates, ...$storage_service->fetchTemplates($type)];
        }

        $items = [
            "title_and_description" => $this->buildTitleAndDescriptionInput($object_settings),
        ];

        if (!$object_settings) {
            $items[self::POST_VAR_FILE_SETTING] = (new FileSettingProperty($templates))->toForm(
                $this->lng,
                $this->ui_factory->input()->field(),
                $this->refinery
            );
            $allow_edit_property = new AllowEditProperty();
            $open_setting_property = new OpenSettingProperty();
        } else {
            $allow_edit_property = new AllowEditProperty(
                $object_settings->isAllowEdit(),
                $object_settings->isLimitedPeriod(),
                $object_settings->getStartTime() ? new DateTimeImmutable($object_settings->getStartTime()->get(IL_CAL_DATETIME)) : null,
                $object_settings->getEndTime() ? new DateTimeImmutable($object_settings->getEndTime()->get(IL_CAL_DATETIME)) : null
            );

            $open_setting_property = new OpenSettingProperty($object_settings->getOpenSetting());
        }

        $items += [
            self::POST_VAR_ONLINE => $this->buildOnlineInput($object_settings),
            self::POST_VAR_EDIT => $allow_edit_property->toForm(
                $this->lng,
                $this->ui_factory->input()->field(),
                $this->refinery
            ),
            self::POST_VAR_OPEN_SETTING => $open_setting_property->toForm(
                $this->lng,
                $this->ui_factory->input()->field(),
                $this->refinery
            )
        ];

        return $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormActionByClass(
                ilObjOnlyOfficeGUI::class,
                $new_object ? "save" : ilObjOnlyOfficeGUI::CMD_SETTINGS_STORE
            ),
            $items
        );
    }

    private function buildTitleAndDescriptionInput(?ObjectSettings $object_settings): FormInput
    {
        $input = (new ilObject())->getObjectProperties()->getPropertyTitleAndDescription()->toForm(
            $this->lng,
            $this->ui_factory->input()->field(),
            $this->refinery
        )->withRequired(false);

        if ($object_settings) {
            $input = $input->withValue([
                $object_settings->getTitle(),
                $object_settings->getDescription()
            ]);
        }
        return $input;
    }

    private function buildOnlineInput(?ObjectSettings $object_settings): FormInput
    {
        $input = $this->ui_factory->input()->field()->checkbox($this->plugin->txt('settings_online'));

        if ($object_settings) {
            $input = $input->withValue($object_settings->isOnline());
        }
        return $input;
    }
}
