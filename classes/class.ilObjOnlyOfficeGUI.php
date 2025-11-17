<?php

require_once __DIR__ . "/../vendor/autoload.php";

use ILIAS\DI\Container;
use ILIAS\Filesystem\Exception\IOException;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\FileUpload\Exception\IllegalStateException;
use ILIAS\FileUpload\Handler\BasicFileInfoResult;
use ILIAS\FileUpload\Handler\BasicHandlerResult;
use ILIAS\FileUpload\Handler\FileInfoResult;
use ILIAS\FileUpload\Handler\HandlerResult;
use ILIAS\Plugin\OnlyOffice\Enum\FileMode;
use ILIAS\Plugin\OnlyOffice\Enum\OpenSetting;
use ILIAS\Plugin\OnlyOffice\Form\Property\AllowEditProperty;
use ILIAS\Plugin\OnlyOffice\Form\Property\FileSettingProperty;
use ILIAS\Plugin\OnlyOffice\Form\ObjectSettingsForm;
use ILIAS\Plugin\OnlyOffice\ObjectSettings\ObjectSettings;
use ILIAS\Plugin\OnlyOffice\Repository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\Common\UUID;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\Plugin\OnlyOffice\Utils\FileSanitizer;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;
use ILIAS\UI\Component\Input\Field\UploadHandler as UploadHandlerInterface;

/**
 * @ilCtrl_isCalledBy ilObjOnlyOfficeGUI: ilRepositoryGUI
 * @ilCtrl_isCalledBy ilObjOnlyOfficeGUI: ilObjPluginDispatchGUI
 * @ilCtrl_isCalledBy ilObjOnlyOfficeGUI: ilAdministrationGUI
 * @ilCtrl_Calls      ilObjOnlyOfficeGUI: ilPermissionGUI
 * @ilCtrl_Calls      ilObjOnlyOfficeGUI: ilInfoScreenGUI
 * @ilCtrl_Calls      ilObjOnlyOfficeGUI: ilObjectCopyGUI
 * @ilCtrl_Calls      ilObjOnlyOfficeGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_Calls      ilObjOnlyOfficeGUI: xonoContentGUI
 * @ilCtrl_Calls      ilObjOnlyOfficeGUI: xonoEditorGUI
 */
class ilObjOnlyOfficeGUI extends ilObjectPluginGUI
{

    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;

    public const CMD_MANAGE_CONTENTS = "manageContents";
    public const CMD_PERMISSIONS = "perm";
    public const CMD_SETTINGS = "settings";
    public const CMD_SETTINGS_STORE = "settingsStore";
    public const CMD_SHOW_CONTENTS = "showContents";
    public const CMD_SHOW_VERSIONS = "showVersions";
    /* standard commands */
    public const CMD_SAVE = 'save';
    public const CMD_CANCEL = 'cancel';
    public const CMD_SHOW_INFO = 'infoScreen';
    public const CMD_TEMPLATE = 'createFromTemplate';

    public const TAB_PERMISSIONS = "perm_settings";
    public const TAB_SETTINGS = "settings";
    public const TAB_INFO = "info_short";
    public const TAB_SHOW_CONTENTS = "show_contents";


    public ilObjOnlyOffice|ilObject|null $object = null;
    protected StorageService $storage_service;
    /**
     * @var ilOnlyOfficePlugin|ilPlugin|null
     */
    protected ?ilPlugin $plugin = null;
    private Repository $repo;
    private Container $dic;

    protected function afterConstructor(): void
    {
        global $DIC;

        $this->repo = Repository::getInstance();
        $this->dic = $DIC;

        $this->storage_service = new StorageService(
            $this->dic,
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );
    }

    final public function getType(): string
    {
        return ilOnlyOfficePlugin::PLUGIN_ID;
    }

    /**
     * @throws ilCtrlException
     */
    public function performCommand(string $cmd): void
    {
        $this->dic->help()->setScreenIdComponent(ilOnlyOfficePlugin::PLUGIN_ID);
        $next_class = $this->dic->ctrl()->getNextClass($this);

        switch (strtolower($next_class)) {
            case strtolower(xonoContentGUI::class):
                if (
                    !ilObjOnlyOfficeAccess::hasReadAccess()
                ) {
                    ilObjOnlyOfficeAccess::redirectNonAccess(ilRepositoryGUI::class);
                }
                $xonoContentGUI = new xonoContentGUI($this->dic, $this->plugin, $this->object_id);
                $this->dic->ctrl()->forwardCommand($xonoContentGUI);
                break;
            case strtolower(xonoEditorGUI::class):
                if (
                    !ilObjOnlyOfficeAccess::hasReadAccess()
                ) {
                    ilObjOnlyOfficeAccess::redirectNonAccess(ilRepositoryGUI::class);
                }

                $xonoEditorGUI = new xonoEditorGUI($this->dic, $this->plugin, $this->obj_id);
                $this->dic->ctrl()->forwardCommand($xonoEditorGUI);
                break;
            default:
                switch ($cmd) {
                    case self::CMD_SHOW_CONTENTS:
                    case self::CMD_MANAGE_CONTENTS:
                        // Read commands
                        if (!ilObjOnlyOfficeAccess::hasReadAccess() &&
                            !$this->repo->objectSettings()->getObjectSettingsById($this->object_id)->allowEdit()) {
                            ilObjOnlyOfficeAccess::redirectNonAccess(ilRepositoryGUI::class);
                        }

                        switch ($this->object->object_settings->getOpen()) {
                            case OpenSetting::DOWNLOAD:
                                $next_cmd = xonoContentGUI::CMD_DOWNLOAD;
                                $file = $this->storage_service->getFile($this->obj_id);
                                if (is_null($file)) {
                                    return;
                                }
                                $file_version = $this->storage_service->getLatestVersion($file->getUuid());
                                $ext = pathinfo($file_version->getUrl(), PATHINFO_EXTENSION);
                                $filename = rtrim($file->getTitle(), '.' . $ext);
                                $this->dic->ctrl()->setParameterByClass(
                                    xonoContentGUI::class,
                                    'path',
                                    ILIAS_ABSOLUTE_PATH . '/data/' . CLIENT_ID . $file_version->getUrl()
                                );
                                $this->dic->ctrl()->setParameterByClass(
                                    xonoContentGUI::class,
                                    'name',
                                    $filename . '_V' . $file_version->getVersion() . '.' . $file->getFileType()
                                );
                                $this->dic->ctrl()->setParameterByClass(
                                    xonoContentGUI::class,
                                    'mime',
                                    $file->getMimeType()
                                );
                                break;
                            case OpenSetting::EDITOR:
                                $next_cmd = xonoContentGUI::CMD_EDIT;
                                break;
                            default: // "ilias" / "0"
                                $next_cmd = xonoContentGUI::CMD_SHOW_VERSIONS;
                        }

                        $this->dic->ctrl()->redirectByClass(xonoContentGUI::class, $next_cmd);
                        break;

                    case self::CMD_SHOW_VERSIONS:
                        $this->dic->ctrl()->redirectByClass(xonoContentGUI::class, xonoContentGUI::CMD_SHOW_VERSIONS);
                        break;

                    case self::CMD_SETTINGS:
                    case self::CMD_SETTINGS_STORE:
                        // Write commands
                        if (!ilObjOnlyOfficeAccess::hasWriteAccess()) {
                            ilObjOnlyOfficeAccess::redirectNonAccess($this);
                        }

                        $this->{$cmd}();
                        break;

                    default:
                        // Unknown command
                        ilObjOnlyOfficeAccess::redirectNonAccess(ilRepositoryGUI::class);
                        break;
                }
                break;
        }
    }

    protected function show(string $html): void
    {
        if (!$this->dic->ctrl()->isAsynch()) {
            $this->dic->ui()->mainTemplate()->setTitle($this->object->getTitle());

            $this->dic->ui()->mainTemplate()->setDescription($this->object->getDescription());

            if (!$this->object->isOnline()) {
                $this->dic->ui()->mainTemplate()->setAlertProperties([
                    [
                        "alert" => true,
                        "property" => $this->plugin->txt("object_status"),
                        "value" => $this->plugin->txt("object_offline")
                    ]
                ]);
            }
        }

        $this->tpl->setContent($html);
    }

    public function initCreateForm(string $a_new_type = null): StandardForm
    {
        return (new ObjectSettingsForm(null, true))->getForm();
    }

    public function uploadFile(): void
    {
        if (!$this->dic->upload()->hasBeenProcessed()) {
            $this->dic->upload()->process();
        }
        $results = $this->dic->upload()->getResults();
        /** @var UploadResult $result */
        $result = end($results);

        $tempName = "";
        if ($result instanceof UploadResult && $result->isOK()) {
            $status = HandlerResult::STATUS_OK;
            $message = 'Upload ok';
            $uuid = new UUID();

            $tmpFilesystem = $this->dic->filesystem()->temp();
            $tempName = $uuid->asString() . "/" . ilFileUtils::getValidFilename($result->getName());
            $tmpFilesystem->createDir($uuid->asString());

            $tmpFilesystem->put(
                $tempName,
                file_get_contents($result->getPath())
            );
        } else {
            $status = HandlerResult::STATUS_FAILED;
            $message = $result->getStatus()->getMessage();
        }

        $responseData =  new BasicHandlerResult(
            UploadHandlerInterface::DEFAULT_FILE_ID_PARAMETER,
            $status,
            $tempName,
            $message
        );
        $content = json_encode($responseData, JSON_THROW_ON_ERROR);

        $response = $this->dic->http()->response()->withBody(Streams::ofString($content));
        $this->dic->http()->saveResponse($response);
        $this->dic->http()->sendResponse();
        $this->dic->http()->close();
    }

    /**
     * @throws IllegalStateException
     * @throws IOException
     * @throws ilDateTimeException
     */
    public function afterSave(ilObjOnlyOffice|ilObject $a_new_object): void
    {
        global $DIC;

        $form = (new ObjectSettingsForm())->getForm()->withRequest($this->request);

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

        /** @var FileSettingProperty $fileSetting */
        $fileSetting = $formData[ObjectSettingsForm::POST_VAR_FILE_SETTING];

        // Handle file upload, otherwise create new document
        if ($fileSetting->getFileMode() === FileMode::UPLOAD) {
            $uploadResult = $fileSetting->getUploadResult();
            if ($uploadResult) {
                if (!$this->dic->upload()->hasBeenProcessed()) {
                    $this->dic->upload()->process();
                }
                $this->storage_service->createNewFileFromUpload(
                    $uploadResult,
                    $a_new_object->getId()
                );

                if ($title === "") {
                    $a_new_object->setTitle(pathinfo($uploadResult->getName(), PATHINFO_FILENAME));
                    $a_new_object->update();
                }
            }
        } elseif ($fileSetting->getFileMode() === FileMode::CREATE) {
            $fileCreationType = $fileSetting->getFileCreationType();

             $this->storage_service->createNewFileFromDraft(
                FileSanitizer::sanitizeFileName($title),
                $fileCreationType->toDocumentType(),
                $a_new_object->getId()
            );
        } elseif ($fileSetting->getFileMode() === FileMode::TEMPLATE) {
            $this->storage_service->createNewFileFromTemplate(
                FileSanitizer::sanitizeFileName($title),
                $fileSetting->getFileTemplate(),
                $a_new_object->getId()
            );
        }

        parent::afterSave($a_new_object);
    }

    protected function settings(?StandardForm $form = null): void
    {
        $this->dic->tabs()->activateTab(self::TAB_SETTINGS);

        if (!$form) {
            $form = (new ObjectSettingsForm($this->object->object_settings))->getForm();
        }

        $this->tpl->setContent($this->ui_renderer->render($form));
    }

    protected function settingsStore(): void
    {
        $this->dic->tabs()->activateTab(self::TAB_SETTINGS);

        /** @var ?StandardForm $form */
        $form = null;
        $this->object->doUpdate($form);

        if ($form->getError()) {
            $this->settings($form);
            return;
        }

        $this->tpl->setOnScreenMessage('success', $this->plugin->txt("saved"), true);

        $this->dic->ctrl()->redirect($this, self::CMD_SETTINGS);
    }

    protected function setTabs(): void
    {
        $this->dic->tabs()->addTab(
            self::TAB_SHOW_CONTENTS,
            $this->plugin->txt("object_show_contents"),
            $this->dic->ctrl()
                ->getLinkTarget(
                    $this,
                    self::CMD_SHOW_VERSIONS
                )
        );
        $this->dic->tabs()->addTab(
            self::TAB_INFO,
            $this->plugin->txt("object_tab_info"),
            $this->dic->ctrl()->getLinkTarget($this, self::CMD_SHOW_INFO)
        );

        if (ilObjOnlyOfficeAccess::hasWriteAccess()) {
            $this->dic->tabs()->addTab(
                self::TAB_SETTINGS,
                $this->plugin->txt("settings_settings"),
                $this->dic->ctrl()
                    ->getLinkTarget(
                        $this,
                        self::CMD_SETTINGS
                    )
            );
        }

        if (ilObjOnlyOfficeAccess::hasEditPermissionAccess()) {
            $this->dic->tabs()->addTab(
                self::TAB_PERMISSIONS,
                $this->lng->txt(self::TAB_PERMISSIONS),
                $this->dic->ctrl()
                    ->getLinkTargetByClass([
                        self::class,
                        ilPermissionGUI::class
                    ], self::CMD_PERMISSIONS)
            );
        }

        //$this->dic->tabs()->manual_activation = true; // Show all tabs as links when no activation
    }

    public static function getStartCmd(): string
    {
        return self::CMD_SHOW_CONTENTS;
    }

    public function getAfterCreationCmd(): string
    {
        return self::getStartCmd();
    }

    public function getStandardCmd(): string
    {
        return self::getStartCmd();
    }
}
