<?php

require_once __DIR__ . "/../vendor/autoload.php";

use ILIAS\DI\Container;
use ILIAS\FileUpload\FileUpload;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\OnlyOffice\Enum\FileMode;
use ILIAS\Plugin\OnlyOffice\Enum\OpenSetting;
use ILIAS\Plugin\OnlyOffice\Form\ObjectSettingsForm;
use ILIAS\Plugin\OnlyOffice\Form\Property\AllowEditProperty;
use ILIAS\Plugin\OnlyOffice\Form\Property\FileSettingProperty;
use ILIAS\Plugin\OnlyOffice\ObjectSettings\ObjectSettings;
use ILIAS\Plugin\OnlyOffice\Repository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\Refinery\Factory;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;

class ilObjOnlyOffice extends ilObjectPlugin
{

    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    public ObjectSettings $object_settings;

    private ilPlugin $pl;
    private $tpl;
    private Factory $refinery;
    private WrapperFactory $httpWrapper;
    private FileUpload $upload;
    private Repository $repo;

    private Container $dic;

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
        $this->pl = $component_factory->getPlugin(ilOnlyOfficePlugin::PLUGIN_ID);
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->repo = Repository::getInstance();
    }

    final public function initType(): void
    {
        $this->setType(ilOnlyOfficePlugin::PLUGIN_ID);
    }

    protected function beforeCreate(): bool
    {
        $form = (new ObjectSettingsForm())->getForm()->withRequest($this->dic->http()->request());
        $formData = $form->getData();

        if ($formData === null) {
            return false;
        }

        /** @var ilObjectPropertyTitleAndDescription $titleAndDescription */
        $titleAndDescription = $formData["title_and_description"];
        $title = $titleAndDescription->getTitle();

        /** @var FileSettingProperty $fileSetting */
        $fileSetting = $formData[ObjectSettingsForm::POST_VAR_FILE_SETTING];
        /** @var AllowEditProperty $allowEdit */
        $allowEdit = $formData[ObjectSettingsForm::POST_VAR_EDIT];

        if ($fileSetting->getFileMode() === FileMode::CREATE && $title === "") {
            $this->tpl->setOnScreenMessage(
                'failure',
                sprintf(
                    $this->pl->txt("settings_file_mode_create_title_required"),
                    $this->pl->txt("form_input_create_file")
                ),
                true
            );
            $this->dic->ctrl()->setParameterByClass(ilObjOnlyOfficeGUI::class, "ref_id", 1);
            $this->dic->ctrl()->setParameterByClass(ilObjOnlyOfficeGUI::class, "new_type", ilOnlyOfficePlugin::PLUGIN_ID);
            $this->dic->ctrl()->redirectByClass([ilRepositoryGUI::class, ilObjOnlyOfficeGUI::class], "create");

            return false;
        }

        if (
            $allowEdit->isLimitedPeriod()
            && $allowEdit->getStartTime()->getTimestamp() >= $allowEdit->getEndTime()->getTimestamp()
        ) {
            $this->tpl->setOnScreenMessage('failure', $this->pl->txt("settings_time_greater_than"), true);
            $this->dic->ctrl()->setParameterByClass(ilObjOnlyOfficeGUI::class, "ref_id", 1);
            $this->dic->ctrl()->setParameterByClass(ilObjOnlyOfficeGUI::class, "new_type", ilOnlyOfficePlugin::PLUGIN_ID);
            $this->dic->ctrl()->redirectByClass([ilRepositoryGUI::class, ilObjOnlyOfficeGUI::class], "create");            return false;
        }
        return parent::beforeCreate();
    }

    /**
     * @throws ilDateTimeException
     */
    public function doCreate(bool $clone_mode = false): void
    {
        $this->object_settings = new ObjectSettings();

        $form = (new ObjectSettingsForm())->getForm()->withRequest($this->dic->http()->request());

        /** @var array{
         *     title_and_description: ilObjectPropertyTitleAndDescription,
         *     file_setting: FileSettingProperty,
         *     online: bool,
         *     allow_edit: AllowEditProperty,
         *     open_setting: OpenSetting
         * } $formData
         */
        $formData = $form->getData();

        /** @var ilObjectPropertyTitleAndDescription $titleAndDescription */
        $titleAndDescription = $formData["title_and_description"];
        $title = $titleAndDescription->getTitle();
        $description = $titleAndDescription->getDescription();

        /** @var FileSettingProperty $fileSetting */
        $fileSetting = $formData[ObjectSettingsForm::POST_VAR_FILE_SETTING];
        /** @var AllowEditProperty $allowEdit */
        $allowEdit = $formData[ObjectSettingsForm::POST_VAR_EDIT];
        /** @var OpenSetting $openSetting */
        $openSetting = $formData[ObjectSettingsForm::POST_VAR_OPEN_SETTING];


        if (
            $fileSetting->getFileMode() === FileMode::UPLOAD
            && $title === ""
            && $fileSetting->getUploadResult()
        ) {
            $uploadResult = $fileSetting->getUploadResult();
            $title = pathinfo($uploadResult->getName(), PATHINFO_FILENAME);
        }

        if ($allowEdit->isLimitedPeriod()) {
            if ($allowEdit->getStartTime()) {
                $this->object_settings->setStartTime($allowEdit->getStartTime()->format("Y-m-d H:i:s"));
            }

            if ($allowEdit->getEndTime()) {
                $this->object_settings->setEndTime($allowEdit->getEndTime()->format("Y-m-d H:i:s"));
            }
        }

        $this->object_settings->setObjId($this->id);
        $this->object_settings->setTitle($title);
        $this->object_settings->setDescription($description);
        $this->object_settings->setAllowEdit($allowEdit->isAllowEdit());
        $this->object_settings->setOnline((bool) $formData[ObjectSettingsForm::POST_VAR_ONLINE]);
        $this->object_settings->setOpen($openSetting);
        $this->object_settings->setLimitedPeriod($allowEdit->isLimitedPeriod());
        $this->repo->objectSettings()->storeObjectSettings($this->object_settings);
    }

    public function doRead(): void
    {
        $this->object_settings = $this->repo->objectSettings()->getObjectSettingsById(intval($this->id));
    }

    /**
     * @throws ilDateTimeException
     */
    public function doUpdate(?StandardForm &$form = null): void
    {
        if ($form === null) {
            $form = (new ObjectSettingsForm($this->object_settings))
                ->getForm()
                ->withRequest($this->dic->http()->request());
        }

        /** @var array{
         *     title_and_description: ilObjectPropertyTitleAndDescription,
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

        /** @var ilObjectPropertyTitleAndDescription $titleAndDescription */
        $titleAndDescription = $formData["title_and_description"];
        $title = $titleAndDescription->getTitle();

        /** @var AllowEditProperty $allowEdit */
        $allowEdit = $formData[ObjectSettingsForm::POST_VAR_EDIT];
        /** @var OpenSetting $openSetting */
        $openSetting = $formData[ObjectSettingsForm::POST_VAR_OPEN_SETTING];

        if ($allowEdit->isLimitedPeriod() && $allowEdit->getStartTime()) {
            $this->object_settings->setStartTime($allowEdit->getStartTime()->format("Y-m-d H:i:s"));
        }

        if ($allowEdit->isLimitedPeriod() && $allowEdit->getEndTime()) {
            $this->object_settings->setEndTime($allowEdit->getEndTime()->format("Y-m-d H:i:s"));
        }

        $this->object_settings->setTitle($title);
        $this->object_settings->setDescription($titleAndDescription->getDescription());
        $this->object_settings->setAllowEdit($allowEdit->isAllowEdit());
        $this->object_settings->setOpen($openSetting);
        $this->object_settings->setOnline((bool) $formData["online"]);
        $this->object_settings->setLimitedPeriod($allowEdit->isLimitedPeriod());
        $this->repo->objectSettings()->storeObjectSettings($this->object_settings);

        $this->setTitle($this->object_settings->getTitle());
        $this->setDescription($this->object_settings->getDescription());
        $this->setOnline($this->object_settings->isOnline());
        $this->getObjectProperties()->storeCoreProperties();
    }

    public function doDelete(): void
    {
        if ($this->object_settings !== null) {
            $this->repo->objectSettings()->deleteObjectSettings($this->object_settings);
        }
        $storage = new StorageService(
            $this->dic,
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );
        $storage->deleteFile($this->getId());

    }

    protected function doCloneObject(
        $new_obj,
        int $a_target_id,
        ?int $a_copy_id = null
    ): void
    {
        $new_obj->object_settings = $this->repo->objectSettings()->cloneObjectSettings($this->object_settings);
        $new_obj->object_settings->setObjId($new_obj->id);
        $this->repo->objectSettings()->storeObjectSettings($new_obj->object_settings);
        $storage = new StorageService(
            $this->dic,
            new ilDBFileVersionRepository(),
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
        return $this->object_settings->allowEdit();
    }
}
