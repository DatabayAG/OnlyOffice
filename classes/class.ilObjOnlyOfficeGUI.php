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
use ILIAS\Filesystem\Exception\IOException;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\FileUpload\Exception\IllegalStateException;
use ILIAS\FileUpload\Handler\BasicHandlerResult;
use ILIAS\FileUpload\Handler\HandlerResult;
use ILIAS\ILIASObject\Properties\CoreProperties\TitleAndDescription;
use ILIAS\Plugin\OnlyOffice\Enum\FileMode;
use ILIAS\Plugin\OnlyOffice\Enum\OpenSetting;
use ILIAS\Plugin\OnlyOffice\Form\Property\AllowEditProperty;
use ILIAS\Plugin\OnlyOffice\Form\Property\FileSettingProperty;
use ILIAS\Plugin\OnlyOffice\Form\ObjectSettingsForm;
use ILIAS\Plugin\OnlyOffice\Utils\FileSanitizer;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;
use ILIAS\UI\Component\Input\Field\UploadHandler as UploadHandlerInterface;
use ILIAS\Data\UUID\Factory as UUIDFactory;

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
    public const string CMD_MANAGE_CONTENTS = "manageContents";
    public const string CMD_PERMISSIONS = "perm";
    public const string CMD_SETTINGS = "settings";
    public const string CMD_SETTINGS_STORE = "settingsStore";
    public const string CMD_SHOW_CONTENTS = "showContents";
    public const string CMD_SHOW_VERSIONS = "showVersions";
    /* standard commands */
    public const string CMD_SAVE = 'save';
    public const string CMD_CANCEL = 'cancel';
    public const string CMD_SHOW_INFO = 'infoScreen';
    public const string CMD_TEMPLATE = 'createFromTemplate';

    public const string TAB_PERMISSIONS = "perm_settings";
    public const string TAB_SETTINGS = "settings";
    public const string TAB_INFO = "info_short";
    public const string TAB_SHOW_CONTENTS = "show_contents";

    public ilObjOnlyOffice|ilObject|null $object = null;
    protected ilPlugin|ilOnlyOfficePlugin|null $plugin = null;
    private Container $dic;
    private UUIDFactory $uuid_factory;

    protected function afterConstructor(): void
    {
        global $DIC;

        $this->dic = $DIC;

        $this->uuid_factory = new UUIDFactory();
    }

    final public function getType(): string
    {
        return ilOnlyOfficePlugin::ID;
    }

    /**
     * @throws ilCtrlException
     */
    public function performCommand(string $cmd): void
    {
        $this->dic->help()->setScreenIdComponent(ilOnlyOfficePlugin::ID);
        $next_class = $this->dic->ctrl()->getNextClass($this);

        switch (strtolower($next_class)) {
            case strtolower(xonoContentGUI::class):
                if (
                    !ilObjOnlyOfficeAccess::hasReadAccess()
                ) {
                    ilObjOnlyOfficeAccess::redirectNonAccess(ilRepositoryGUI::class);
                }
                $xono_content_gui = new xonoContentGUI($this->dic, $this->plugin, $this->object_id);
                $this->dic->ctrl()->forwardCommand($xono_content_gui);
                break;
            case strtolower(xonoEditorGUI::class):
                if (
                    !ilObjOnlyOfficeAccess::hasReadAccess()
                ) {
                    ilObjOnlyOfficeAccess::redirectNonAccess(ilRepositoryGUI::class);
                }

                $xono_editor_gui = new xonoEditorGUI($this->dic, $this->plugin, $this->obj_id);
                $this->dic->ctrl()->forwardCommand($xono_editor_gui);
                break;
            default:
                switch ($cmd) {
                    case self::CMD_SHOW_CONTENTS:
                    case self::CMD_MANAGE_CONTENTS:

                        // Read commands
                        if (!ilObjOnlyOfficeAccess::hasReadAccess() &&
                            !$this->object->object_settings->isAllowEdit()) {
                            ilObjOnlyOfficeAccess::redirectNonAccess(ilRepositoryGUI::class);
                        }

                        switch ($this->object->object_settings->getOpenSetting()) {
                            case OpenSetting::DOWNLOAD:
                                $storage_service = $this->plugin->getStorageService();

                                $next_cmd = xonoContentGUI::CMD_DOWNLOAD;
                                $file = $storage_service->getFile($this->obj_id);
                                if (is_null($file)) {
                                    return;
                                }
                                $file_version = $storage_service->getLatestVersion($file->getUuid());
                                $this->dic->ctrl()->setParameterByClass(
                                    xonoContentGUI::class,
                                    "version",
                                    $file_version->getVersion()
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

    public function save(): void
    {
        $form = (new ObjectSettingsForm())->getForm()->withRequest($this->dic->http()->request());
        $form_data = $form->getData();

        if ($form_data === null) {
            return;
        }

        /** @var TitleAndDescription $title_and_description */
        $title_and_description = $form_data["title_and_description"];
        $title = $title_and_description->getTitle();

        /** @var FileSettingProperty $file_setting */
        $file_setting = $form_data[ObjectSettingsForm::POST_VAR_FILE_SETTING];
        /** @var AllowEditProperty $allow_edit */
        $allow_edit = $form_data[ObjectSettingsForm::POST_VAR_EDIT];

        if ($file_setting->getFileMode() === FileMode::CREATE && $title === "") {
            $this->tpl->setOnScreenMessage(
                'failure',
                sprintf(
                    $this->plugin->txt("settings_file_mode_create_title_required"),
                    $this->plugin->txt("form_input_create_file")
                ),
                true
            );
            $this->dic->ctrl()->setParameterByClass(self::class, "ref_id", 1);
            $this->dic->ctrl()->setParameterByClass(self::class, "new_type", ilOnlyOfficePlugin::ID);
            $this->dic->ctrl()->redirectByClass([ilRepositoryGUI::class, self::class], "create");

            return;
        }

        if (
            $allow_edit->isLimitedPeriod()
            && $allow_edit->getStartTime()->getTimestamp() >= $allow_edit->getEndTime()->getTimestamp()
        ) {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("settings_time_greater_than"), true);
            $this->dic->ctrl()->setParameterByClass(self::class, "ref_id", 1);
            $this->dic->ctrl()->setParameterByClass(self::class, "new_type", ilOnlyOfficePlugin::ID);
            $this->dic->ctrl()->redirectByClass([ilRepositoryGUI::class, self::class], "create");
            return;
        }

        parent::save();
    }

    public function initCreateForm(string $new_type = null): StandardForm
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

        $temp_name = "";
        if ($result instanceof UploadResult && $result->isOK()) {
            $status = HandlerResult::STATUS_OK;
            $message = 'Upload ok';
            $uuid = $this->uuid_factory->uuid4();

            $tmp_filesystem = $this->dic->filesystem()->temp();
            $temp_name = $uuid->toString() . "/" . ilFileUtils::getValidFilename($result->getName());
            $tmp_filesystem->createDir($uuid->toString());

            $tmp_filesystem->put(
                $temp_name,
                file_get_contents($result->getPath())
            );
        } else {
            $status = HandlerResult::STATUS_FAILED;
            $message = $result->getStatus()->getMessage();
        }

        $response_data = new BasicHandlerResult(
            UploadHandlerInterface::DEFAULT_FILE_ID_PARAMETER,
            $status,
            $temp_name,
            $message
        );
        $content = json_encode($response_data, JSON_THROW_ON_ERROR);

        $response = $this->dic->http()->response()->withBody(Streams::ofString($content));
        $this->dic->http()->saveResponse($response);
        $this->dic->http()->sendResponse();
        $this->dic->http()->close();
    }

    /**
     * @throws IllegalStateException
     * @throws IOException
     */
    public function afterSave(ilObjOnlyOffice|ilObject $new_object): void
    {
        $storage_service = $this->plugin->getStorageService();

        $form = (new ObjectSettingsForm())->getForm()->withRequest($this->request);

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

        /** @var FileSettingProperty $file_setting */
        $file_setting = $form_data[ObjectSettingsForm::POST_VAR_FILE_SETTING];

        // Handle file upload, otherwise create new document
        if ($file_setting->getFileMode() === FileMode::UPLOAD) {
            $upload_result = $file_setting->getUploadResult();
            if ($upload_result) {
                if (!$this->dic->upload()->hasBeenProcessed()) {
                    $this->dic->upload()->process();
                }
                $storage_service->createNewFileFromUpload(
                    $upload_result,
                    $new_object->getId()
                );

                if ($title === "") {
                    $new_object->setTitle(pathinfo($upload_result->getName(), PATHINFO_FILENAME));
                    $new_object->update();
                }
            }
        } elseif ($file_setting->getFileMode() === FileMode::CREATE) {
            $file_creation_type = $file_setting->getFileCreationType();

            $storage_service->createNewFileFromDraft(
                FileSanitizer::sanitizeFileName($title),
                $file_creation_type->toDocumentType(),
                $new_object->getId()
            );
        } elseif ($file_setting->getFileMode() === FileMode::TEMPLATE) {
            $storage_service->createNewFileFromTemplate(
                FileSanitizer::sanitizeFileName($title),
                $file_setting->getFileTemplate(),
                $new_object->getId()
            );
        }

        parent::afterSave($new_object);
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
