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

use ILIAS\DI\Container;
use ILIAS\FileUpload\FileUpload;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\ILIASObject\Properties\CoreProperties\TitleAndDescription;
use ILIAS\Plugin\OnlyOffice\Enum\FileMode;
use ILIAS\Plugin\OnlyOffice\Enum\OpenSetting;
use ILIAS\Plugin\OnlyOffice\Form\ObjectSettingsForm;
use ILIAS\Plugin\OnlyOffice\Form\Property\AllowEditProperty;
use ILIAS\Plugin\OnlyOffice\Form\Property\FileSettingProperty;
use ILIAS\Plugin\OnlyOffice\Repository\ObjectSettingsRepository;
use ILIAS\Refinery\Factory;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;
use ILIAS\Plugin\OnlyOffice\Model\ObjectSettings;

class ilObjOnlyOffice extends ilObjectPlugin
{
    public ObjectSettings $object_settings;

    protected ilOnlyOfficePlugin|ilPlugin|null $plugin = null;
    private readonly ilGlobalTemplateInterface $main_tpl;
    private readonly Factory $refinery;
    private readonly WrapperFactory $http_wrapper;
    private readonly FileUpload $upload;

    private readonly Container $dic;
    private readonly ObjectSettingsRepository $object_settings_repo;

    public function __construct(int $a_ref_id = 0)
    {
        global $DIC;

        $this->plugin = ilOnlyOfficePlugin::getInstance();

        parent::__construct($a_ref_id);

        $this->dic = $DIC;
        $this->refinery = $this->dic->refinery();
        $this->http_wrapper = $this->dic->http()->wrapper();
        $this->upload = $this->dic->upload();
        $this->main_tpl = $this->dic->ui()->mainTemplate();

        $this->object_settings_repo = new ObjectSettingsRepository($this->dic->database());
    }

    final public function initType(): void
    {
        $this->setType(ilOnlyOfficePlugin::ID);
    }

    public function doCreate(bool $clone_mode = false): void
    {
        if ($clone_mode) {
            return;
        }

        $form = (new ObjectSettingsForm())->getForm()->withRequest($this->dic->http()->request());

        /** @var array{
         *     title_and_description: TitleAndDescription,
         *     file_setting: FileSettingProperty,
         *     online: bool,
         *     allow_edit: AllowEditProperty,
         *     open_setting: OpenSetting
         * } $form_data
         */
        $form_data = $form->getData();

        /** @var TitleAndDescription $title_and_description */
        $title_and_description = $form_data["title_and_description"];
        $title = $title_and_description->getTitle();
        $description = $title_and_description->getDescription();

        /** @var FileSettingProperty $file_setting */
        $file_setting = $form_data[ObjectSettingsForm::POST_VAR_FILE_SETTING];
        /** @var AllowEditProperty $allow_edit */
        $allow_edit = $form_data[ObjectSettingsForm::POST_VAR_EDIT];
        /** @var OpenSetting $open_setting */
        $open_setting = $form_data[ObjectSettingsForm::POST_VAR_OPEN_SETTING];

        $upload_result = $file_setting->getUploadResult();
        if (
            $file_setting->getFileMode() === FileMode::UPLOAD
            && $title === ""
            && $upload_result
        ) {
            $title = pathinfo($upload_result->getName(), PATHINFO_FILENAME);
        }

        $this->object_settings = new ObjectSettings(
            $this->getId(),
            $title,
            $description,
            $open_setting,
            (bool) $form_data[ObjectSettingsForm::POST_VAR_ONLINE],
            $allow_edit->isAllowEdit(),
            $allow_edit->isLimitedPeriod(),
            ($allow_edit->isLimitedPeriod() && $allow_edit->getStartTime())
                ? new ilDateTime($allow_edit->getStartTime()->format("Y-m-d H:i:s"), IL_CAL_DATETIME)
                : null,
            ($allow_edit->isLimitedPeriod() && $allow_edit->getEndTime())
                ? new ilDateTime($allow_edit->getEndTime()->format("Y-m-d H:i:s"), IL_CAL_DATETIME)
                : null,
        );

        $this->object_settings_repo->create($this->object_settings);
    }

    public function doRead(): void
    {
        $this->object_settings = $this->object_settings_repo->read($this->getId()) ?? new ObjectSettings(
            $this->getId(),
            "unknown",
            "object does not exist"
        );
    }

    public function doUpdate(?StandardForm &$form = null): void
    {
        $form ??= (new ObjectSettingsForm(null, true))
            ->getForm()
            ->withRequest($this->dic->http()->request());

        /** @var array{
         *     title_and_description: TitleAndDescription,
         *     file_setting: FileSettingProperty,
         *     online: bool,
         *     allow_edit: AllowEditProperty,
         *     open_setting: OpenSetting
         * }|null $form_data
         */
        $form_data = $form->getData();

        if (!$form_data) {
            return;
        }

        /** @var TitleAndDescription $title_and_description */
        $title_and_description = $form_data["title_and_description"];
        $title = $title_and_description->getTitle();

        /** @var AllowEditProperty $allow_edit */
        $allow_edit = $form_data[ObjectSettingsForm::POST_VAR_EDIT];
        /** @var OpenSetting $open_setting */
        $open_setting = $form_data[ObjectSettingsForm::POST_VAR_OPEN_SETTING];

        if ($allow_edit->isLimitedPeriod() && $allow_edit->getStartTime()) {
            $this->object_settings->setStartTime(new ilDateTime($allow_edit->getStartTime()->format("Y-m-d H:i:s"), IL_CAL_DATETIME));
        }

        if ($allow_edit->isLimitedPeriod() && $allow_edit->getEndTime()) {
            $this->object_settings->setEndTime(new ilDateTime($allow_edit->getEndTime()->format("Y-m-d H:i:s"), IL_CAL_DATETIME));
        }

        $this->object_settings->setTitle($title);
        $this->object_settings->setDescription($title_and_description->getDescription());
        $this->object_settings->setAllowEdit($allow_edit->isAllowEdit());
        $this->object_settings->setOpenSetting($open_setting);
        $this->object_settings->setOnline((bool) $form_data["online"]);
        $this->object_settings->setLimitedPeriod($allow_edit->isLimitedPeriod());
        $this->object_settings_repo->update($this->object_settings);

        $this->setTitle($this->object_settings->getTitle());
        $this->setDescription($this->object_settings->getDescription());
        $this->setOnline($this->object_settings->isOnline());
        $this->getObjectProperties()->storeCoreProperties();
    }

    public function doDelete(): void
    {
        $this->object_settings_repo->delete($this->object_settings);
        $this->plugin->getStorageService()->delete($this->getId());

    }

    /**
     * @param ilObjOnlyOffice $new_obj
     */
    protected function doCloneObject(
        $new_obj,
        int $a_target_id,
        ?int $a_copy_id = null
    ): void {
        $new_obj->object_settings = new ObjectSettings(
            $new_obj->getId(),
            $this->object_settings->getTitle(),
            $this->object_settings->getDescription(),
            $this->object_settings->getOpenSetting(),
            $this->object_settings->isOnline(),
            $this->object_settings->isAllowEdit(),
            $this->object_settings->isLimitedPeriod(),
            $this->object_settings->getStartTime(),
            $this->object_settings->getEndTime()
        );

        $this->object_settings_repo->store($new_obj->object_settings);

        $this->plugin->getStorageService()->createClone($new_obj->getId(), $this->getId());
    }

    public function isOnline(): bool
    {
        return $this->object_settings->isOnline();
    }

    public function setOnline(bool $is_online = true): void
    {
        $this->object_settings->setOnline($is_online);
    }

    public function isAllowedEdit(): bool
    {
        return $this->object_settings->isAllowEdit();
    }
}
