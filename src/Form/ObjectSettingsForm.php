<?php

namespace ILIAS\Plugin\OnlyOffice\Form;

use DateTimeImmutable;
use ilCtrlInterface;
use ILIAS\DI\Container;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Form\Property\AllowEditProperty;
use ILIAS\Plugin\OnlyOffice\Form\Property\FileSettingProperty;
use ILIAS\Plugin\OnlyOffice\Form\Property\OpenSettingProperty;
use ILIAS\Plugin\OnlyOffice\ObjectSettings\ObjectSettings;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;
use ilLanguage;
use ilObject;
use ilObjOnlyOfficeGUI;
use ilOnlyOfficePlugin;
use ILIAS\UI\Factory;

class ObjectSettingsForm
{
    public const OPTION_SETTING_CREATE = "create_file";
    public const OPTION_SETTING_UPLOAD = "upload_file";
    public const OPTION_SETTING_TEMPLATE = "template_file";

    public const POST_VAR_FILE = 'upload_files';
    public const POST_VAR_FILE_SETTING = 'file_setting';
    public const POST_VAR_FILE_CREATION_SETTING = 'file_creation_setting';
    public const POST_VAR_FILE_TEMPLATE_SETTING = 'file_template_setting';
    public const POST_VAR_OPEN_SETTING = 'open_setting';
    public const POST_VAR_ONLINE = 'online';
    public const POST_VAR_EDIT = 'allow_edit';
    public const POST_VAR_EDIT_LIMITED = 'allow_edit_limited';
    public const POST_VAR_EDIT_LIMITED_START = 'start_time';
    public const POST_VAR_EDIT_LIMITED_END = 'end_time';
    public const POST_VAR_CREATE = 'createFrom';

    private WrapperFactory $httpWrapper;
    private ilOnlyOfficePlugin $plugin;
    private Container $dic;
    private Factory $uiFactory;
    private StorageService $storage_service;
    private ilCtrlInterface $ctrl;
    private ilLanguage $lng;
    private \ILIAS\Refinery\Factory $refinery;
    private StandardForm $form;

    public function __construct(?ObjectSettings $objectSettings = null, bool $newObject = false)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->uiFactory = $this->dic->ui()->factory();
        $this->plugin = ilOnlyOfficePlugin::getInstance();
        $this->ctrl = $this->dic->ctrl();
        $this->lng = $this->dic->language();
        $this->refinery = $this->dic->refinery();

        $this->storage_service = new StorageService(
            $this->dic,
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );

        $this->form = $this->buildForm($objectSettings, $newObject);
    }

    public function getForm(): StandardForm
    {
        return $this->form;
    }

    private function buildForm(?ObjectSettings $objectSettings, bool $newObject): StandardForm
    {
        $inputField = $this->uiFactory->input()->field();

        // file template option
        $text_templates = $this->storage_service->fetchTemplates(FileCreationType::TEXT);
        $table_templates = $this->storage_service->fetchTemplates(FileCreationType::TABLE);
        $presentation_templates = $this->storage_service->fetchTemplates(FileCreationType::PRESENTATION);
        $templates = array_merge($text_templates, $table_templates, $presentation_templates);

        $items = [
            "title_and_description" => $this->buildTitleAndDescriptionInput($objectSettings),
        ];

        if (!$objectSettings) {
            $items[self::POST_VAR_FILE_SETTING] = (new FileSettingProperty($templates))->toForm(
                $this->lng,
                $this->uiFactory->input()->field(),
                $this->refinery
            );
            $allowEditProperty = new AllowEditProperty();
            $openSettingProperty = new OpenSettingProperty();
        } else {
            $allowEditProperty = new AllowEditProperty(
                $objectSettings->allowEdit(),
                $objectSettings->isLimitedPeriod(),
                $objectSettings->getStartTime() ? new DateTimeImmutable($objectSettings->getStartTime()) : null,
                $objectSettings->getEndTime() ? new DateTimeImmutable($objectSettings->getEndTime()) : null
            );

            $openSettingProperty = new OpenSettingProperty($objectSettings->getOpen());
        }

        $items += [
            self::POST_VAR_ONLINE => $this->buildOnlineInput($objectSettings),
            self::POST_VAR_EDIT => $allowEditProperty->toForm(
                $this->lng,
                $this->uiFactory->input()->field(),
                $this->refinery
            ),
            self::POST_VAR_OPEN_SETTING => $openSettingProperty->toForm(
                $this->lng,
                $this->uiFactory->input()->field(),
                $this->refinery
            )
        ];

        return $this->uiFactory->input()->container()->form()->standard(
            $this->ctrl->getFormActionByClass(
                ilObjOnlyOfficeGUI::class,
                $newObject ? "save" : ilObjOnlyOfficeGUI::CMD_SETTINGS_STORE
            ),
            $items
        );
    }

    private function buildTitleAndDescriptionInput(?ObjectSettings $objectSettings): FormInput
    {
        $input = (new ilObject())->getObjectProperties()->getPropertyTitleAndDescription()->toForm(
            $this->lng,
            $this->uiFactory->input()->field(),
            $this->refinery
        )->withRequired(false);

        if ($objectSettings) {
            $input = $input->withValue([
                $objectSettings->getTitle(),
                $objectSettings->getDescription()
            ]);
        }
        return $input;
    }

    private function buildOnlineInput(?ObjectSettings $objectSettings): FormInput
    {
        $input = $this->uiFactory->input()->field()->checkbox($this->plugin->txt('settings_online'));

        if ($objectSettings) {
            $input = $input->withValue($objectSettings->isOnline());
        }
        return $input;
    }
}
