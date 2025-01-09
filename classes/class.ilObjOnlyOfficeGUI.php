<?php

require_once __DIR__ . "/../vendor/autoload.php";
use ILIAS\Filesystem\Exception\IOException;
use ILIAS\FileUpload\Exception\IllegalStateException;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use srag\Plugins\OnlyOffice\ObjectSettings\ObjectSettingsFormGUI;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use srag\Plugins\OnlyOffice\StorageService\StorageService;
use srag\Plugins\OnlyOffice\Utils\FileSanitizer;
use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use srag\DIC\OnlyOffice\DICTrait;
use srag\Plugins\OnlyOffice\InfoService\InfoService;

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
    use DICTrait;
    use OnlyOfficeTrait;

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
    public const LANG_MODULE_OBJECT = "object";
    public const LANG_MODULE_SETTINGS = "settings";

    public const TAB_PERMISSIONS = "perm_settings";
    public const TAB_SETTINGS = "settings";
    public const TAB_INFO = "info_short";
    public const TAB_SHOW_CONTENTS = "show_contents";

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

    public const FILE_EXTENSIONS = [
        "text" => "docx",
        "table" => "xlsx",
        "presentation" => "pptx"
    ];

    public ?ilObject $object = null;
    protected StorageService $storage_service;
    /**
     * @var ilOnlyOfficePlugin|ilPlugin|null
     */
    protected ?ilPlugin $plugin = null;

    protected function afterConstructor(): void
    {
        $this->storage_service = new StorageService(
            self::dic()->dic(),
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
        self::dic()->help()->setScreenIdComponent(ilOnlyOfficePlugin::PLUGIN_ID);
        $next_class = self::dic()->ctrl()->getNextClass($this);

        switch (strtolower($next_class)) {
            case strtolower(xonoContentGUI::class):
                $xonoContentGUI = new xonoContentGUI(self::dic()->dic(), $this->plugin, $this->object_id);
                self::dic()->ctrl()->forwardCommand($xonoContentGUI);
                break;
            case strtolower(xonoEditorGUI::class):
                $xonoEditorGUI = new xonoEditorGUI(self::dic()->dic(), $this->plugin, $this->obj_id);
                self::dic()->ctrl()->forwardCommand($xonoEditorGUI);
                break;
            default:
                switch ($cmd) {
                    case self::CMD_SHOW_CONTENTS:
                    case self::CMD_MANAGE_CONTENTS:
                        // Read commands
                        if (!ilObjOnlyOfficeAccess::hasReadAccess() &&
                            !self::onlyOffice()->objectSettings()->getObjectSettingsById($this->object_id)->allowEdit()) {
                            ilObjOnlyOfficeAccess::redirectNonAccess(ilRepositoryGUI::class);
                        }
                        $open_setting = InfoService::getOpenSetting($this->obj_id);
                        switch ($open_setting) {
                            case "download":
                                $next_cmd = xonoContentGUI::CMD_DOWNLOAD;
                                $file = $this->storage_service->getFile($this->obj_id);
                                if (is_null($file)) {
                                    return;
                                }
                                $file_version = $this->storage_service->getLatestVersion($file->getUuid());
                                $ext = pathinfo($file_version->getUrl(), PATHINFO_EXTENSION);
                                $filename = rtrim($file->getTitle(), '.' . $ext);
                                self::dic()->ctrl()->setParameterByClass(
                                    xonoContentGUI::class,
                                    'path',
                                    ILIAS_ABSOLUTE_PATH . '/data/' . CLIENT_ID . $file_version->getUrl()
                                );
                                self::dic()->ctrl()->setParameterByClass(
                                    xonoContentGUI::class,
                                    'name',
                                    $filename . '_V' . $file_version->getVersion() . '.' . $file->getFileType()
                                );
                                self::dic()->ctrl()->setParameterByClass(
                                    xonoContentGUI::class,
                                    'mime',
                                    $file->getMimeType()
                                );
                                break;
                            case "editor":
                                $next_cmd = xonoContentGUI::CMD_EDIT;
                                break;
                            default: // "ilias" / "0"
                                $next_cmd = xonoContentGUI::CMD_SHOW_VERSIONS;
                        }

                        self::dic()->ctrl()->redirectByClass(xonoContentGUI::class, $next_cmd);
                        break;

                    case self::CMD_SHOW_VERSIONS:
                        self::dic()->ctrl()->redirectByClass(xonoContentGUI::class, xonoContentGUI::CMD_SHOW_VERSIONS);
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
        if (!self::dic()->ctrl()->isAsynch()) {
            self::dic()->ui()->mainTemplate()->setTitle($this->object->getTitle());

            self::dic()->ui()->mainTemplate()->setDescription($this->object->getDescription());

            if (!$this->object->isOnline()) {
                self::dic()->ui()->mainTemplate()->setAlertProperties([
                    [
                        "alert" => true,
                        "property" => self::plugin()->translate("status", self::LANG_MODULE_OBJECT),
                        "value" => self::plugin()->translate("offline", self::LANG_MODULE_OBJECT)
                    ]
                ]);
            }
        }

        self::output()->output($html);
    }

    protected function initCreationForms(string $a_new_type): array
    {
        $forms = parent::initCreationForms($a_new_type);
        return $forms;
    }

    public function initCreateForm(string $a_new_type = null): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTarget("_top");
        $form->setFormAction($this->ctrl->getFormAction($this, "save"));
        $form->setTitle($this->txt("xono_new"));

        // title
        $ti = new ilTextInputGUI($this->lng->txt("title"), "title");
        $ti->setSize(min(40, ilObject::TITLE_LENGTH));
        $ti->setMaxLength(ilObject::TITLE_LENGTH);
        $ti->setInfo(self::plugin()->translate("create_title_info"));
        $ti->setRequired(true);
        $ti->setMaxLength(100);
        $form->addItem($ti);

        // description
        $ta = new ilTextAreaInputGUI($this->lng->txt("description"), "desc");
        $ta->setCols(40);
        $ta->setRows(2);
        $form->addItem($ta);

        // file
        $file_settings = new ilRadioGroupInputGUI(
            self::plugin()->translate('form_input_file'),
            self::POST_VAR_FILE_SETTING
        );

        // file upload option
        $file_input = new ilFileInputGUI(self::plugin()->translate('form_input_file'), self::POST_VAR_FILE);
        $file_input->setRequired(true);

        $file_settings_upload_option = new ilRadioOption(self::plugin()->translate('form_input_upload_file'), self::OPTION_SETTING_UPLOAD);
        $file_settings_upload_option->addSubItem($file_input);
        $file_settings->addOption($file_settings_upload_option);

        // file create option
        $file_creation_settings = new ilRadioGroupInputGUI(
            "",
            self::POST_VAR_FILE_CREATION_SETTING
        );
        $file_creation_settings->addOption(new ilRadioOption(self::plugin()->translate('form_input_create_file_text'), "text"));
        $file_creation_settings->addOption(new ilRadioOption(self::plugin()->translate('form_input_create_file_table'), "table"));
        $file_creation_settings->addOption(new ilRadioOption(self::plugin()->translate('form_input_create_file_presentation'), "presentation"));
        $file_creation_settings->setRequired(true);

        $file_settings_create_option = new ilRadioOption(self::plugin()->translate('form_input_create_file'), self::OPTION_SETTING_CREATE);
        $file_settings_create_option->addSubItem($file_creation_settings);
        $file_settings->addOption($file_settings_create_option);

        // file template option
        $text_templates = $this->storage_service->fetchTemplates("text");
        $table_templates = $this->storage_service->fetchTemplates("table");
        $presentation_templates = $this->storage_service->fetchTemplates("presentation");
        $templates = array_merge($text_templates, $table_templates, $presentation_templates);

        $template_settings = new ilRadioGroupInputGUI(
            "",
            self::POST_VAR_FILE_TEMPLATE_SETTING
        );

        foreach ($templates as $template) {
            $type_translation = sprintf("form_template_%s", $template->getType());
            $description = empty($template->getDescription()) ? "-" : $template->getDescription();
            $option = new ilRadioOption(sprintf("%s %s", $template->getTitle(), self::plugin()->translate($type_translation)), $template->getPath());
            if (!empty($template->getDescription())) {
                $option->setInfo($template->getDescription());
            }
            $template_settings->addOption($option);
        }

        $template_settings->setRequired(true);

        $file_settings_template_option = new ilRadioOption(self::plugin()->translate('form_input_template'), self::OPTION_SETTING_TEMPLATE);
        $file_settings_template_option->addSubItem($template_settings);

        if (count($templates) >= 1) {
            $file_settings->addOption($file_settings_template_option);
        } else {
            $file_settings->setInfo(self::plugin()->translate('form_input_template_no_templates'));
        }

        $file_settings->setValue("ilias");
        $file_settings->setRequired(true);
        $form->addItem($file_settings);

        // online checkbox
        $online = new ilCheckboxInputGUI(
            self::plugin()->translate('online', ilObjOnlyOfficeGUI::LANG_MODULE_SETTINGS),
            self::POST_VAR_ONLINE
        );
        $form->addItem($online);

        // Users are allowed to edit checkbox
        $edit = new ilCheckboxInputGUI(self::plugin()->translate(
            'allow_edit',
            ilObjOnlyOfficeGUI::LANG_MODULE_SETTINGS
        ), self::POST_VAR_EDIT);
        $edit->setInfo(self::plugin()->translate(
            'allow_edit_info',
            ilObjOnlyOfficeGUI::LANG_MODULE_SETTINGS
        ));
        $edit->setChecked(true);

        $lim_period = new ilCheckboxInputGUI(self::plugin()->translate(
            'allow_edit_limited',
            ilObjOnlyOfficeGUI::LANG_MODULE_SETTINGS
        ), self::POST_VAR_EDIT_LIMITED);

        $start_date_time = new ilDateTimeInputGUI(self::plugin()->translate(
            'allow_edit_limited_start',
            ilObjOnlyOfficeGUI::LANG_MODULE_SETTINGS
        ), self::POST_VAR_EDIT_LIMITED_START);
        $start_date_time->setShowTime(true);
        $start_date_time->setRequired(true);
        $end_date_time = new ilDateTimeInputGUI(self::plugin()->translate(
            'allow_edit_limited_end',
            ilObjOnlyOfficeGUI::LANG_MODULE_SETTINGS
        ), self::POST_VAR_EDIT_LIMITED_END);
        $end_date_time->setShowTime(true);
        $end_date_time->setRequired(true);

        $lim_period->addSubItem($start_date_time);
        $lim_period->addSubItem($end_date_time);

        $edit->addSubItem($lim_period);
        $form->addItem($edit);

        // Settings for opening a file
        $opening_setting = new ilRadioGroupInputGUI(
            self::plugin()->translate("form_open_setting"),
            self::POST_VAR_OPEN_SETTING
        );
        $opening_setting->addOption(new ilRadioOption(self::plugin()->translate(
            "open_setting_editor",
            self::LANG_MODULE_SETTINGS
        ), "editor"));
        $opening_setting->addOption(new ilRadioOption(self::plugin()->translate(
            "open_setting_ilias",
            self::LANG_MODULE_SETTINGS
        ), "ilias"));
        $opening_setting->addOption(new ilRadioOption(self::plugin()->translate(
            "open_setting_download",
            self::LANG_MODULE_SETTINGS
        ), "download"));
        $opening_setting->setValue("editor");
        $opening_setting->setRequired(true);
        $form->addItem($opening_setting);

        // Buttons
        $form->addCommandButton("save", $this->txt("xono_add"));
        $form->addCommandButton("cancel", $this->lng->txt("cancel"));

        return $form;
    }

    /**
     * @param ilObject|ilObjOnlyOffice $a_new_object
     * @throws IllegalStateException
     * @throws IOException
     * @throws ilDateTimeException
     */
    public function afterSave(/*ilObjOnlyOffice*/ ilObject $a_new_object): void
    {
        global $DIC;
        $httpWrapper = $DIC->http()->wrapper();

        $form = $this->initCreateForm($a_new_object->getType());
        $form->checkInput();

        $fileSetting = $httpWrapper->post()->retrieve(
            self::POST_VAR_FILE_SETTING,
            $this->refinery->kindlyTo()->string()
        );

        // Handle file upload, otherwise create new document
        if ($fileSetting === self::OPTION_SETTING_UPLOAD) {
            if (!self::dic()->upload()->hasBeenProcessed()) {
                self::dic()->upload()->process();
            }
            $results = self::dic()->upload()->getResults();
            $result = end($results);
            $this->storage_service->createNewFileFromUpload($result, $a_new_object->getId());

            $title = $a_new_object->getTitle();
            if ($title === "") {
                $a_new_object->setTitle(explode(".", $result->getName())[0]);
                $a_new_object->update();
            }
        } elseif ($fileSetting === self::OPTION_SETTING_CREATE) {
            $title = $httpWrapper->post()->retrieve(
                "title",
                $this->refinery->kindlyTo()->string()
            );

            $sanitized_file_name = FileSanitizer::sanitizeFileName($title);

            $template = $this->storage_service->createNewFileFromDraft(
                $sanitized_file_name,
                $a_new_object->getId()
            );
        } elseif ($fileSetting === self::OPTION_SETTING_TEMPLATE) {
            $title = $httpWrapper->post()->retrieve(
                "title",
                $this->refinery->kindlyTo()->string()
            );
            $sanitized_file_name = FileSanitizer::sanitizeFileName($title);

            $templatePath = $httpWrapper->post()->retrieve(
                self::POST_VAR_FILE_TEMPLATE_SETTING,
                $this->refinery->kindlyTo()->string()
            );

            $this->storage_service->createNewFileFromTemplate(
                $sanitized_file_name,
                $templatePath,
                $a_new_object->getId()
            );
        }

        parent::afterSave($a_new_object);
    }

    protected function getSettingsForm(): ObjectSettingsFormGUI
    {
        $form = new ObjectSettingsFormGUI($this, $this->object);
        return $form;
    }

    protected function settings(): void
    {
        self::dic()->tabs()->activateTab(self::TAB_SETTINGS);

        $form = $this->getSettingsForm();

        self::output()->output($form);
    }

    protected function settingsStore(): void
    {
        self::dic()->tabs()->activateTab(self::TAB_SETTINGS);

        $form = $this->getSettingsForm();

        if (!$form->storeForm()) {
            self::output()->output($form);

            return;
        }
        $this->tpl->setOnScreenMessage('success', $this->plugin->txt("saved"), true);

        self::dic()->ctrl()->redirect($this, self::CMD_SETTINGS);
    }

    protected function setTabs(): void
    {
        self::dic()->tabs()->addTab(
            self::TAB_SHOW_CONTENTS,
            self::plugin()->translate("show_contents", self::LANG_MODULE_OBJECT),
            self::dic()->ctrl()
                                                                                      ->getLinkTarget(
                                                                                          $this,
                                                                                          self::CMD_SHOW_VERSIONS
                                                                                      )
        );
        self::dic()->tabs()->addTab(
            self::TAB_INFO,
            self::plugin()->translate("tab_info", self::LANG_MODULE_OBJECT),
            self::dic()->ctrl()->getLinkTarget($this, self::CMD_SHOW_INFO)
        );

        if (ilObjOnlyOfficeAccess::hasWriteAccess()) {
            self::dic()->tabs()->addTab(
                self::TAB_SETTINGS,
                self::plugin()->translate("settings", self::LANG_MODULE_SETTINGS),
                self::dic()->ctrl()
                                                                                       ->getLinkTarget(
                                                                                           $this,
                                                                                           self::CMD_SETTINGS
                                                                                       )
            );
        }

        if (ilObjOnlyOfficeAccess::hasEditPermissionAccess()) {
            self::dic()->tabs()->addTab(
                self::TAB_PERMISSIONS,
                self::plugin()->translate(self::TAB_PERMISSIONS, "", [], false),
                self::dic()->ctrl()
                                                                                     ->getLinkTargetByClass([
                                                                                         self::class,
                                                                                         ilPermissionGUI::class
                                                                                     ], self::CMD_PERMISSIONS)
            );
        }

        //self::dic()->tabs()->manual_activation = true; // Show all tabs as links when no activation
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
