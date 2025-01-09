<?php

require_once __DIR__ . "/../vendor/autoload.php";

use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;
use srag\Plugins\OnlyOffice\StorageService\DTO\FileTemplate;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use srag\Plugins\OnlyOffice\StorageService\StorageService;
use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use srag\DIC\OnlyOffice\DICTrait;

/**
 * @ilCtrl_IsCalledBy  ilOnlyOfficeConfigGUI: ilObjComponentSettingsGUI
 */
class ilOnlyOfficeConfigGUI extends ilPluginConfigGUI
{
    use DICTrait;
    use OnlyOfficeTrait;
    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    public const CMD_CONFIGURE = "configure";
    public const CMD_TEMPLATES = "configureTemplates";
    public const CMD_CREATE_TEMPLATE = "createTemplate";
    public const CMD_EDIT_TEMPLATE = "editTemplate";
    public const CMD_SAVE_EDIT_TEMPLATE = "saveEditTemplate";
    public const CMD_DELETE_TEMPLATE = "deleteTemplate";
    public const CMD_UPDATE_CONFIGURE = "updateConfigure";
    public const CMD_UPDATE_TEMPLATES = "updateTemplates";
    public const CMD_CONFIRM_DELETE = "confirmDelete";
    public const LANG_MODULE = "config";
    public const TAB_CONFIGURATION = "configuration";
    public const TAB_SUB_CONFIGURATION = "subConfiguration";
    public const TAB_SUB_TEMPLATES = "templates";
    protected StorageService $storage_service;
    private ilPlugin $pl;
    private $tpl;
    private Factory $refinery;
    private WrapperFactory $httpWrapper;

    public function __construct()
    {
        global $DIC;
        $this->refinery = $DIC->refinery();
        $this->httpWrapper = $DIC->http()->wrapper();

        $this->storage_service = new StorageService(
            self::dic()->dic(),
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );

        /** @var $component_factory ilComponentFactory */
        $component_factory = $DIC['component.factory'];
        /** @var $plugin ilOnlyOfficePlugin */
        $this->pl = $component_factory->getPlugin(ilOnlyOfficePlugin::PLUGIN_ID);
        $this->tpl = $DIC["tpl"];
    }

    public function performCommand(string $cmd): void
    {
        $this->setTabs();

        $next_class = self::dic()->ctrl()->getNextClass($this);

        switch (strtolower($next_class)) {
            default:
                $cmd = self::dic()->ctrl()->getCmd();

                switch ($cmd) {
                    case self::CMD_CONFIGURE:
                    case self::CMD_TEMPLATES:
                    case self::CMD_CREATE_TEMPLATE:
                    case self::CMD_SAVE_EDIT_TEMPLATE:
                    case self::CMD_EDIT_TEMPLATE:
                    case self::CMD_DELETE_TEMPLATE:
                    case self::CMD_UPDATE_CONFIGURE:
                    case self::CMD_UPDATE_TEMPLATES:
                    case self::CMD_CONFIRM_DELETE:
                        if (!ilObjOnlyOfficeAccess::hasWriteAccess()) {
                            ilObjOnlyOfficeAccess::redirectNonAccess($this);
                        }
                        $this->{$cmd}();
                        break;

                    default:
                        break;
                }
                break;
        }
    }

    protected function setTabs(): void
    {
        self::dic()->tabs()->addTab(self::TAB_CONFIGURATION, self::plugin()->translate("configuration", self::LANG_MODULE), self::dic()->ctrl()
            ->getLinkTargetByClass(self::class, self::CMD_CONFIGURE));

        self::dic()->tabs()->addSubTab(self::TAB_SUB_CONFIGURATION, self::plugin()->translate("tab_general", self::LANG_MODULE), self::dic()->ctrl()
            ->getLinkTargetByClass(self::class, self::CMD_CONFIGURE));

        self::dic()->tabs()->addSubTab(self::TAB_SUB_TEMPLATES, self::plugin()->translate("tab_templates", self::LANG_MODULE), self::dic()->ctrl()
            ->getLinkTargetByClass(self::class, self::CMD_TEMPLATES));

        self::dic()->locator()->addItem(ilOnlyOfficePlugin::PLUGIN_NAME, self::dic()->ctrl()->getLinkTarget($this, self::CMD_CONFIGURE));
    }

    protected function configure(): void
    {
        self::dic()->tabs()->activateTab(self::TAB_CONFIGURATION);
        self::dic()->tabs()->activateSubTab(self::TAB_SUB_CONFIGURATION);

        $form = self::onlyOffice()->config()->factory()->newFormInstance($this);

        self::output()->output($form);
    }

    protected function configureTemplates(): void
    {
        self::dic()->tabs()->activateTab(self::TAB_CONFIGURATION);
        self::dic()->tabs()->activateSubTab(self::TAB_SUB_TEMPLATES);

        global $ilToolbar;

        $ilToolbar->addButton(
            self::plugin()->translate("create_template", self::LANG_MODULE),
            self::dic()->ctrl()->getLinkTargetByClass(self::class, self::CMD_CREATE_TEMPLATE)
        );

        $tpl = self::plugin()->template("html/tpl.config_create_template.html");

        $text_templates = $this->storage_service->fetchTemplates("text");
        $table_templates = $this->storage_service->fetchTemplates("table");
        $presentation_templates = $this->storage_service->fetchTemplates("presentation");
        $templates = array_merge($text_templates, $table_templates, $presentation_templates);

        if (count($templates) >= 1) {
            $tpl->setVariable('TYPE_HEADER', self::plugin()->translate("table_type", self::LANG_MODULE));
            $tpl->setVariable('TITLE_HEADER', self::plugin()->translate("table_title", self::LANG_MODULE));
            $tpl->setVariable('DESCRIPTION_HEADER', self::plugin()->translate("table_description", self::LANG_MODULE));
            $tpl->setVariable('EXTENSION_HEADER', self::plugin()->translate("table_extension", self::LANG_MODULE));
            $tpl->setVariable('SETTINGS_HEADER', self::plugin()->translate("table_settings", self::LANG_MODULE));
        }

        /** @var FileTemplate $template */
        foreach ($templates as $template) {
            $tpl->setCurrentBlock("entry");
            $tpl->setVariable('TITLE', $template->getTitle());
            $tpl->setVariable('TYPE', self::plugin()->translate("form_input_create_file_" . $template->getType()));
            $tpl->setVariable('DESCRIPTION', empty($template->getDescription()) ? "-" : $template->getDescription());
            $tpl->setVariable('EXTENSION', $template->getExtension());
            $ctrlFormat = "%s&ootarget=%s&ooextension=%s";

            $ilSelect = new ilAdvancedSelectionListGUI();
            $ilSelect->setListTitle(self::plugin()->translate("table_options", self::LANG_MODULE));
            $ilSelect->addItem(
                self::plugin()->translate("table_edit", self::LANG_MODULE),
                "",
                self::dic()->ctrl()->getLinkTargetByClass(
                    self::class,
                    sprintf($ctrlFormat, self::CMD_EDIT_TEMPLATE, urlencode($template->getTitle()), urlencode($template->getExtension()))
                )
            );
            $ilSelect->addItem(
                self::plugin()->translate("table_delete", self::LANG_MODULE),
                "",
                self::dic()->ctrl()->getLinkTargetByClass(
                    self::class,
                    sprintf($ctrlFormat, self::CMD_CONFIRM_DELETE, urlencode($template->getTitle()), urlencode($template->getExtension()))
                )
            );
            $tpl->setVariable('SETTINGS', $ilSelect->getHTML());
            $tpl->parseCurrentBlock();
        }

        $content = $tpl->get();
        self::output()->output($content);
    }

    protected function createTemplate(): void
    {
        self::dic()->tabs()->activateTab(self::TAB_CONFIGURATION);
        self::dic()->tabs()->activateSubTab(self::TAB_SUB_TEMPLATES);

        $form = $this->initCreateTemplateForm()->getHTML();
        self::output()->output($form);
    }

    protected function initCreateTemplateForm(bool $edit = false): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTarget("_top");

        $ooTarget = $this->httpWrapper->query()->retrieve(
            "ootarget",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $ooExtension = $this->httpWrapper->query()->retrieve(
            "ooextension",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $form->setFormAction(self::dic()->ctrl()->getFormAction($this) . "&prevTitle=" . urlencode($ooTarget) . "&prevExtension=" . urlencode($ooExtension));

        // title
        $ti = new ilTextInputGUI(self::plugin()->translate("table_title", self::LANG_MODULE), "title");
        $ti->setSize(min(40, ilObject::TITLE_LENGTH));
        $ti->setMaxLength(ilObject::TITLE_LENGTH);
        $form->addItem($ti);

        // description
        $ta = new ilTextAreaInputGUI(self::plugin()->translate("table_description", self::LANG_MODULE), "desc");
        $ta->setCols(40);
        $ta->setRows(2);
        $form->addItem($ta);

        // file upload option
        $file_input = new ilFileInputGUI(self::plugin()->translate("form_input_file"), "file");
        $file_input->setRequired(!$edit);
        $form->addItem($file_input);

        if ($edit) {
            $form->setTitle(self::plugin()->translate("edit_template", self::LANG_MODULE));
            $form->addCommandButton(self::CMD_SAVE_EDIT_TEMPLATE, self::plugin()->translate("settings_save"));
        } else {
            $form->setTitle(self::plugin()->translate("create_template", self::LANG_MODULE));
            $form->addCommandButton(self::CMD_UPDATE_TEMPLATES, self::plugin()->translate("settings_save"));
        }

        $form->addCommandButton(self::CMD_TEMPLATES, self::plugin()->translate("settings_cancel"));

        return $form;
    }

    protected function updateConfigure(): void
    {
        self::dic()->tabs()->activateTab(self::TAB_CONFIGURATION);

        $form = self::onlyOffice()->config()->factory()->newFormInstance($this);

        if (!$form->storeForm()) {
            self::output()->output($form);

            return;
        }
        $this->tpl->setOnScreenMessage('success', $this->pl->txt("config_configuration_saved"), true);
        self::dic()->ctrl()->redirect($this, self::CMD_CONFIGURE);
    }

    protected function updateTemplates(): void
    {
        $form = $this->initCreateTemplateForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            self::output()->output($form);
            return;
        }

        if (!self::dic()->upload()->hasBeenProcessed()) {
            self::dic()->upload()->process();
        }

        $results = self::dic()->upload()->getResults();
        $result = end($results);

        global $DIC;
        $fileServiceSettings = $DIC->fileServiceSettings();
        $extension = pathinfo($result->getName(), PATHINFO_EXTENSION);

        // Return if file extension not whitelisted by ILIAS instance
        if (!in_array($extension, $fileServiceSettings->getWhiteListedSuffixes(), true) || in_array($extension, $fileServiceSettings->getBlackListedSuffixes(), true)) {
            $this->tpl->setOnScreenMessage('failure', $this->pl->txt("config_template_invalid_extension"), true);
            $form->setValuesByPost();
            self::output()->output($form);
            return;
        }

        $title = $this->httpWrapper->post()->retrieve(
            "title",
            $this->refinery->kindlyTo()->string()
        );

        $description = $this->httpWrapper->post()->retrieve(
            "desc",
            $this->refinery->kindlyTo()->string()
        );

        $path = $this->storage_service->createFileTemplate($result, $title, $description);

        // Return if file extension not recognized by OnlyOffice
        if (empty($path)) {
            $this->tpl->setOnScreenMessage('failure', $this->pl->txt("config_template_unrecognised_extension"), true);
            $form->setValuesByPost();
            self::output()->output($form);
            return;
        }

        $this->tpl->setOnScreenMessage('success', $this->pl->txt("config_template_saved"), true);
        self::dic()->ctrl()->redirect($this, self::CMD_TEMPLATES);
    }

    protected function editTemplate(): void
    {
        self::dic()->tabs()->activateTab(self::TAB_CONFIGURATION);
        self::dic()->tabs()->activateSubTab(self::TAB_SUB_TEMPLATES);

        $target = $this->httpWrapper->query()->retrieve(
            "ootarget",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $extension = $this->httpWrapper->query()->retrieve(
            "ooextension",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $template = $this->storage_service->fetchTemplate($target, $extension);

        if (!is_null($template)) {
            $value_array = [
                "title" => $template->getTitle(),
                "desc" => $template->getDescription(),
                "file" => $template->getPath()
            ];

            $form = $this->initCreateTemplateForm(true);
            $form->setValuesByArray($value_array);
            self::output()->output($form->getHTML());
        }

    }

    protected function saveEditTemplate(): void
    {
        $target = $this->httpWrapper->post()->retrieve(
            "title",
            $this->refinery->kindlyTo()->string()
        );
        $description = $this->httpWrapper->post()->retrieve(
            "desc",
            $this->refinery->kindlyTo()->string()
        );

        $prevTitle = $this->httpWrapper->query()->retrieve(
            "prevTitle",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $prevExtension = $this->httpWrapper->query()->retrieve(
            "prevExtension",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );


        $form = $this->initCreateTemplateForm(true);

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            self::output()->output($form);
            return;
        }

        // If no file is uploaded, merely change title and description
        if (!self::dic()->upload()->hasUploads()) {
            // Dont delete previous template
            $this->storage_service->modifyFileTemplate($prevTitle, $prevExtension, $target, $description);
        } else {
            if (!self::dic()->upload()->hasBeenProcessed()) {
                self::dic()->upload()->process();
            }
            $results = self::dic()->upload()->getResults();
            $result = end($results);

            // Return if file extension not whitelisted by ILIAS instance
            if (!ilFileUtils::getValidFilename($result->getName())) {
                // Fix bug where previous title and name don't get saved into the form action
                $adjustedUrl = str_replace("prevTitle=", "prevTitle=" . urlencode($prevTitle), $form->getFormAction());
                $adjustedUrl = str_replace("prevExtension=", "prevExtension=" . urlencode($prevExtension), $adjustedUrl);
                $form->setFormAction($adjustedUrl);
                $this->tpl->setOnScreenMessage('failure', $this->pl->txt("config_template_invalid_extension"), true);
                $template = $this->storage_service->fetchTemplate($prevTitle, $prevExtension);
                $value_array = [
                    "title" => $target,
                    "desc" => $description,
                    "file" => $template->getPath()
                ];
                $form->setValuesByArray($value_array);
                self::output()->output($form);
                return;
            }

            // Return if file extension not recognized by OnlyOffice
            if (empty($path)) {
                // Fix bug where previous title and name don't get saved into the form action
                $adjustedUrl = str_replace("prevTitle=", "prevTitle=" . urlencode($prevTitle), $form->getFormAction());
                $adjustedUrl = str_replace("prevExtension=", "prevExtension=" . urlencode($prevExtension), $adjustedUrl);
                $form->setFormAction($adjustedUrl);

                $this->tpl->setOnScreenMessage('failure', $this->pl->txt("config_template_unrecognised_extension"), true);
                $template = $this->storage_service->fetchTemplate($prevTitle, $prevExtension);
                $value_array = [
                    "title" => $target,
                    "desc" => $description,
                    "file" => $template->getPath()
                ];
                $form->setValuesByArray($value_array);
                self::output()->output($form);
                return;
            }

            $success = $this->storage_service->deleteFileTemplate($target, $prevExtension);
            $path = $this->storage_service->createFileTemplate($result, $target, $description);
        }

        $this->tpl->setOnScreenMessage('success', $this->pl->txt("config_template_edited"), true);

        self::dic()->ctrl()->redirect($this, self::CMD_TEMPLATES);
    }

    public function confirmDelete(): void
    {
        self::dic()->ctrl()->saveParameter($this, "ootarget");
        self::dic()->ctrl()->saveParameter($this, "ooextension");

        $conf = new ilConfirmationGUI();
        $conf->setFormAction(self::dic()->ctrl()->getFormAction($this));
        $conf->setHeaderText(self::plugin()->translate('config_template_delete'));

        $ooTarget = $this->httpWrapper->query()->retrieve(
            "ootarget",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $conf->addItem('tableview', 1, $ooTarget);

        $conf->setConfirm(self::dic()->language()->txt('delete'), self::CMD_DELETE_TEMPLATE);
        $conf->setCancel(self::dic()->language()->txt('cancel'), self::CMD_TEMPLATES);

        self::output()->output($conf->getHTML());
    }

    protected function deleteTemplate(): void
    {
        $target = $this->httpWrapper->query()->retrieve(
            "ootarget",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $extension = $this->httpWrapper->query()->retrieve(
            "ooextension",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $success = $this->storage_service->deleteFileTemplate($target, $extension);

        if ($success) {
            $this->tpl->setOnScreenMessage('success', $this->pl->txt("config_template_deleted"), true);
        }

        self::dic()->ctrl()->redirect($this, self::CMD_TEMPLATES);
    }
}
