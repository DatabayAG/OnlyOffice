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
use ILIAS\Plugin\OnlyOffice\Repository\FileVersionRepository;
use ILIAS\Plugin\OnlyOffice\Repository\ObjectSettingsRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\Refinery\Factory;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;
use ILIAS\Plugin\OnlyOffice\Model\ObjectSettings;

class ilObjOnlyOffice extends ilObjectPlugin
{
    public ObjectSettings $object_settings;

    private readonly ilPlugin $pl;
    private readonly ilGlobalTemplateInterface $tpl;
    private readonly Factory $refinery;
    private readonly WrapperFactory $httpWrapper;
    private readonly FileUpload $upload;

    private readonly Container $dic;
    private readonly ObjectSettingsRepository $object_settings_repo;

    public function __construct(int $a_ref_id = 0)
    {
        global $DIC;

        parent::__construct($a_ref_id);

        $this->dic = $DIC;
        $this->refinery = $DIC->refinery();
        $this->httpWrapper = $DIC->http()->wrapper();
        $this->upload = $DIC->upload();

        /** @var $component_factory ilComponentFactory */
        $component_factory = $DIC['component.factory'];
        /** @var $plugin ilOnlyOfficePlugin */
        $this->pl = $component_factory->getPlugin(ilOnlyOfficePlugin::ID);
        $this->tpl = $DIC->ui()->mainTemplate();

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
         * } $formData
         */
        $formData = $form->getData();

        /** @var TitleAndDescription $titleAndDescription */
        $titleAndDescription = $formData["title_and_description"];
        $title = $titleAndDescription->getTitle();
        $description = $titleAndDescription->getDescription();

        /** @var FileSettingProperty $fileSetting */
        $fileSetting = $formData[ObjectSettingsForm::POST_VAR_FILE_SETTING];
        /** @var AllowEditProperty $allowEdit */
        $allowEdit = $formData[ObjectSettingsForm::POST_VAR_EDIT];
        /** @var OpenSetting $openSetting */
        $openSetting = $formData[ObjectSettingsForm::POST_VAR_OPEN_SETTING];

        $uploadResult = $fileSetting->getUploadResult();
        if (
            $fileSetting->getFileMode() === FileMode::UPLOAD
            && $title === ""
            && $uploadResult
        ) {
            $title = pathinfo($uploadResult->getName(), PATHINFO_FILENAME);
        }

        $this->object_settings = new ObjectSettings(
            $this->getId(),
            $title,
            $description,
            $openSetting,
            (bool) $formData[ObjectSettingsForm::POST_VAR_ONLINE],
            $allowEdit->isAllowEdit(),
            $allowEdit->isLimitedPeriod(),
            ($allowEdit->isLimitedPeriod() && $allowEdit->getStartTime())
                ? new ilDateTime($allowEdit->getStartTime()->format("Y-m-d H:i:s"), IL_CAL_DATETIME)
                : null,
            ($allowEdit->isLimitedPeriod() && $allowEdit->getEndTime())
                ? new ilDateTime($allowEdit->getEndTime()->format("Y-m-d H:i:s"), IL_CAL_DATETIME)
                : null,
        );

        $this->object_settings_repo->create($this->object_settings);
    }

    public function doRead(): void
    {
        $this->object_settings = $this->object_settings_repo->read($this->getId());
    }

    public function doUpdate(?StandardForm &$form = null): void
    {
        $form ??= (new ObjectSettingsForm($this->object_settings))
            ->getForm()
            ->withRequest($this->dic->http()->request());

        /** @var array{
         *     title_and_description: TitleAndDescription,
         *     file_setting: FileSettingProperty,
         *     online: bool,
         *     allow_edit: AllowEditProperty,
         *     open_setting: OpenSetting
         * }|null $formData
         */
        $formData = $form->getData();

        if (!$formData) {
            return;
        }

        /** @var TitleAndDescription $titleAndDescription */
        $titleAndDescription = $formData["title_and_description"];
        $title = $titleAndDescription->getTitle();

        /** @var AllowEditProperty $allowEdit */
        $allowEdit = $formData[ObjectSettingsForm::POST_VAR_EDIT];
        /** @var OpenSetting $openSetting */
        $openSetting = $formData[ObjectSettingsForm::POST_VAR_OPEN_SETTING];

        if ($allowEdit->isLimitedPeriod() && $allowEdit->getStartTime()) {
            $this->object_settings->setStartTime(new ilDateTime($allowEdit->getStartTime()->format("Y-m-d H:i:s"), IL_CAL_DATETIME));
        }

        if ($allowEdit->isLimitedPeriod() && $allowEdit->getEndTime()) {
            $this->object_settings->setEndTime(new ilDateTime($allowEdit->getEndTime()->format("Y-m-d H:i:s"), IL_CAL_DATETIME));
        }

        $this->object_settings->setTitle($title);
        $this->object_settings->setDescription($titleAndDescription->getDescription());
        $this->object_settings->setAllowEdit($allowEdit->isAllowEdit());
        $this->object_settings->setOpenSetting($openSetting);
        $this->object_settings->setOnline((bool) $formData["online"]);
        $this->object_settings->setLimitedPeriod($allowEdit->isLimitedPeriod());
        $this->object_settings_repo->update($this->object_settings);

        $this->setTitle($this->object_settings->getTitle());
        $this->setDescription($this->object_settings->getDescription());
        $this->setOnline($this->object_settings->isOnline());
        $this->getObjectProperties()->storeCoreProperties();
    }

    public function doDelete(): void
    {
        $this->object_settings_repo->delete($this->object_settings);

        $storage = new StorageService(
            $this->dic,
            new FileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );
        $storage->deleteFile($this->getId());

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

        $storage = new StorageService(
            $this->dic,
            new FileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );
        $storage->createClone($new_obj->getId(), $this->getId());
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
