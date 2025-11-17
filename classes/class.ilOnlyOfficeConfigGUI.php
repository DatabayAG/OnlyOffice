<?php

require_once __DIR__ . "/../vendor/autoload.php";

use ILIAS\DI\Container;
use ILIAS\FileUpload\DTO\ProcessingStatus;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\OnlyOffice\Form\PluginConfigForm;
use ILIAS\Plugin\OnlyOffice\Form\TemplateForm;
use ILIAS\Plugin\OnlyOffice\Repository;
use ILIAS\Refinery\Factory;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\FileTemplate;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;

/**
 * @ilCtrl_IsCalledBy  ilOnlyOfficeConfigGUI: ilObjComponentSettingsGUI
 */
class ilOnlyOfficeConfigGUI extends ilPluginConfigGUI
{
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
    public const TAB_CONFIGURATION = "configuration";
    public const TAB_SUB_CONFIGURATION = "subConfiguration";
    public const TAB_SUB_TEMPLATES = "templates";
    protected StorageService $storage_service;
    private ilPlugin $pl;
    private $tpl;
    private Factory $refinery;
    private WrapperFactory $httpWrapper;
    private Repository $repo;
    private Container $dic;
    private ilOnlyOfficePlugin $plugin;

    public function __construct()
    {
        global $DIC;
        $this->refinery = $DIC->refinery();
        $this->httpWrapper = $DIC->http()->wrapper();
        $this->dic = $DIC;

        $this->storage_service = new StorageService(
            $this->dic,
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );

        /** @var $component_factory ilComponentFactory */
        $component_factory = $DIC['component.factory'];
        /** @var $plugin ilOnlyOfficePlugin */
        $this->plugin = ilOnlyOfficePlugin::getInstance();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->repo = Repository::getInstance();
    }

    public function performCommand(string $cmd): void
    {
        $this->setTabs();

        $next_class = $this->dic->ctrl()->getNextClass($this);

        switch (strtolower($next_class)) {
            default:
                $cmd = $this->dic->ctrl()->getCmd();

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
        $this->dic->tabs()->addTab(self::TAB_CONFIGURATION, $this->plugin->txt("config_configuration"), $this->dic->ctrl()
            ->getLinkTargetByClass(self::class, self::CMD_CONFIGURE));

        $this->dic->tabs()->addSubTab(self::TAB_SUB_CONFIGURATION, $this->plugin->txt("config_tab_general"), $this->dic->ctrl()
            ->getLinkTargetByClass(self::class, self::CMD_CONFIGURE));

        $this->dic->tabs()->addSubTab(self::TAB_SUB_TEMPLATES, $this->plugin->txt("config_tab_templates"), $this->dic->ctrl()
            ->getLinkTargetByClass(self::class, self::CMD_TEMPLATES));

        /** @var ilLocatorGUI $locator */
        $locator = $this->dic["ilLocator"];
        $locator->addItem(ilOnlyOfficePlugin::PLUGIN_NAME, $this->dic->ctrl()->getLinkTarget($this, self::CMD_CONFIGURE));
    }

    protected function configure(?PluginConfigForm $form = null): void
    {
        $this->dic->tabs()->activateTab(self::TAB_CONFIGURATION);
        $this->dic->tabs()->activateSubTab(self::TAB_SUB_CONFIGURATION);

        if (!$form) {
            $form = new PluginConfigForm();
            $form->setValuesByArray([
                PluginConfigForm::KEY_ONLYOFFICE_URL => $this->plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_URL),
                PluginConfigForm::KEY_ONLYOFFICE_SECRET => $this->plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_SECRET),
                PluginConfigForm::KEY_NUM_VERSIONS => (int) $this->plugin->settings->get(PluginConfigForm::KEY_NUM_VERSIONS, 10),
            ], true);
        }

        $this->tpl->setContent($form->getHTML());
    }

    protected function configureTemplates(): void
    {
        $this->dic->tabs()->activateTab(self::TAB_CONFIGURATION);
        $this->dic->tabs()->activateSubTab(self::TAB_SUB_TEMPLATES);

        global $ilToolbar;

        $ilToolbar->addButton(
            $this->plugin->txt("config_create_template"),
            $this->dic->ctrl()->getLinkTargetByClass(self::class, self::CMD_CREATE_TEMPLATE)
        );

        $tpl = $this->plugin->getTemplate("html/tpl.config_create_template.html");

        $text_templates = $this->storage_service->fetchTemplates("text");
        $table_templates = $this->storage_service->fetchTemplates("table");
        $presentation_templates = $this->storage_service->fetchTemplates("presentation");
        $templates = array_merge($text_templates, $table_templates, $presentation_templates);

        if (count($templates) >= 1) {
            $tpl->setVariable('TYPE_HEADER', $this->plugin->txt("config_table_type"));
            $tpl->setVariable('TITLE_HEADER', $this->plugin->txt("config_table_title"));
            $tpl->setVariable('DESCRIPTION_HEADER', $this->plugin->txt("config_table_description"));
            $tpl->setVariable('EXTENSION_HEADER', $this->plugin->txt("config_table_extension"));
            $tpl->setVariable('SETTINGS_HEADER', $this->plugin->txt("config_table_settings"));
        }

        /** @var FileTemplate $template */
        foreach ($templates as $template) {
            $tpl->setCurrentBlock("entry");
            $tpl->setVariable('TITLE', $template->getTitle());
            $tpl->setVariable('TYPE', $this->plugin->txt("form_input_create_file_" . $template->getType()->value));
            $tpl->setVariable('DESCRIPTION', empty($template->getDescription()) ? "-" : $template->getDescription());
            $tpl->setVariable('EXTENSION', $template->getExtension());
            $ctrlFormat = "%s&ootarget=%s&ooextension=%s";

            $uiFactory = $this->dic->ui()->factory();

            $actions = $uiFactory->dropdown()->standard([
                $uiFactory->link()->standard(
                    $this->plugin->txt("config_table_edit"),
                    $this->dic->ctrl()->getLinkTargetByClass(
                        self::class,
                        sprintf($ctrlFormat, self::CMD_EDIT_TEMPLATE, urlencode($template->getTitle()), urlencode($template->getExtension()))
                    )
                ),
                $uiFactory->link()->standard(
                    $this->plugin->txt("config_table_delete"),
                    $this->dic->ctrl()->getLinkTargetByClass(
                        self::class,
                        sprintf($ctrlFormat, self::CMD_CONFIRM_DELETE, urlencode($template->getTitle()), urlencode($template->getExtension()))
                    )
                )
            ])->withLabel($this->plugin->txt("config_table_options"));

            $tpl->setVariable('SETTINGS', $this->dic->ui()->renderer()->render($actions));
            $tpl->parseCurrentBlock();
        }

        $content = $tpl->get();
        $this->tpl->setContent($content);
    }

    protected function createTemplate(?TemplateForm $form = null): void
    {
        $this->dic->tabs()->activateTab(self::TAB_CONFIGURATION);
        $this->dic->tabs()->activateSubTab(self::TAB_SUB_TEMPLATES);

        if (!$form) {
            $form = new TemplateForm();
        }
        $this->tpl->setContent($form->getHTML());
    }

    protected function updateConfigure(): void
    {
        $this->dic->tabs()->activateTab(self::TAB_CONFIGURATION);

        $form = new PluginConfigForm($this);

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->configure($form);
            return;
        }

        $form->setValuesByPost();

        $this->plugin->settings->set(
            PluginConfigForm::KEY_ONLYOFFICE_URL,
            $form->getInput(PluginConfigForm::KEY_ONLYOFFICE_URL)
        );
        $this->plugin->settings->set(
            PluginConfigForm::KEY_ONLYOFFICE_SECRET,
            $form->getInput(PluginConfigForm::KEY_ONLYOFFICE_SECRET)
        );
        $this->plugin->settings->set(
            PluginConfigForm::KEY_NUM_VERSIONS,
            (string) $form->getInput(PluginConfigForm::KEY_NUM_VERSIONS)
        );

        $this->tpl->setOnScreenMessage(
            ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
            $this->plugin->txt("config_configuration_saved"),
            true
        );
        $this->dic->ctrl()->redirect($this, self::CMD_CONFIGURE);
    }

    protected function updateTemplates(): void
    {
        $form = new TemplateForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->createTemplate($form);
            return;
        }

        if (!$this->dic->upload()->hasBeenProcessed()) {
            $this->dic->upload()->process();
        }

        $results = $this->dic->upload()->getResults();
        $result = end($results);

        global $DIC;
        $fileServiceSettings = $DIC->fileServiceSettings();
        $extension = pathinfo($result->getName(), PATHINFO_EXTENSION);

        // Return if file extension not whitelisted by ILIAS instance
        if (!in_array($extension, $fileServiceSettings->getWhiteListedSuffixes(), true) || in_array($extension, $fileServiceSettings->getBlackListedSuffixes(), true)) {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("config_template_invalid_extension"), true);
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
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
        if ($path === null) {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("config_template_unrecognised_extension"), true);
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            return;
        }

        $this->tpl->setOnScreenMessage('success', $this->plugin->txt("config_template_saved"), true);
        $this->dic->ctrl()->redirect($this, self::CMD_TEMPLATES);
    }

    protected function editTemplate(): void
    {
        $this->dic->tabs()->activateTab(self::TAB_CONFIGURATION);
        $this->dic->tabs()->activateSubTab(self::TAB_SUB_TEMPLATES);

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

            $form = new TemplateForm(true);
            $form->setValuesByArray($value_array);
            $this->tpl->setContent($form->getHTML());
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

        $form = new TemplateForm(true);

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            return;
        }

        if (!$this->dic->upload()->hasBeenProcessed()) {
            $this->dic->upload()->process();
        }

        $hasUploads = false;
        $results = $this->dic->upload()->getResults();
        /**
         * @var UploadResult $result
         */
        $result = end($results);
        if ($result->getStatus()->getCode() !== ProcessingStatus::REJECTED) {
            $hasUploads = true;
        }

        // If no file is uploaded, merely change title and description
        if (!$hasUploads) {
            // Dont delete previous template
            $this->storage_service->modifyFileTemplate($prevTitle, $prevExtension, $target, $description);
        } else {
            // Return if file extension not whitelisted by ILIAS instance
            if (!ilFileUtils::getValidFilename($result->getName())) {
                // Fix bug where previous title and name don't get saved into the form action
                $adjustedUrl = str_replace("prevTitle=", "prevTitle=" . urlencode($prevTitle), $form->getFormAction());
                $adjustedUrl = str_replace("prevExtension=", "prevExtension=" . urlencode($prevExtension), $adjustedUrl);
                $form->setFormAction($adjustedUrl);
                $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("config_template_invalid_extension"), true);
                $template = $this->storage_service->fetchTemplate($prevTitle, $prevExtension);
                $value_array = [
                    "title" => $target,
                    "desc" => $description,
                    "file" => $template->getPath()
                ];
                $form->setValuesByArray($value_array);
                $this->tpl->setContent($form->getHTML());
                return;
            }

            // Return if file extension not recognized by OnlyOffice
            if (empty($path)) {
                // Fix bug where previous title and name don't get saved into the form action
                $adjustedUrl = str_replace("prevTitle=", "prevTitle=" . urlencode($prevTitle), $form->getFormAction());
                $adjustedUrl = str_replace("prevExtension=", "prevExtension=" . urlencode($prevExtension), $adjustedUrl);
                $form->setFormAction($adjustedUrl);

                $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("config_template_unrecognised_extension"), true);
                $template = $this->storage_service->fetchTemplate($prevTitle, $prevExtension);
                $value_array = [
                    "title" => $target,
                    "desc" => $description,
                    "file" => $template->getPath()
                ];
                $form->setValuesByArray($value_array);
                $this->tpl->setContent($form->getHTML());
                return;
            }

            $success = $this->storage_service->deleteFileTemplate($target, $prevExtension);
            $path = $this->storage_service->createFileTemplate($result, $target, $description);
        }

        $this->tpl->setOnScreenMessage('success', $this->plugin->txt("config_template_edited"), true);

        $this->dic->ctrl()->redirect($this, self::CMD_TEMPLATES);
    }

    public function confirmDelete(): void
    {
        $this->dic->ctrl()->saveParameter($this, "ootarget");
        $this->dic->ctrl()->saveParameter($this, "ooextension");

        $conf = new ilConfirmationGUI();
        $conf->setFormAction($this->dic->ctrl()->getFormAction($this));
        $conf->setHeaderText($this->plugin->txt('config_template_delete'));

        $ooTarget = $this->httpWrapper->query()->retrieve(
            "ootarget",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $conf->addItem('tableview', 1, $ooTarget);

        $conf->setConfirm($this->dic->language()->txt('delete'), self::CMD_DELETE_TEMPLATE);
        $conf->setCancel($this->dic->language()->txt('cancel'), self::CMD_TEMPLATES);

        $this->tpl->setContent($conf->getHTML());
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
            $this->tpl->setOnScreenMessage('success', $this->plugin->txt("config_template_deleted"), true);
        }

        $this->dic->ctrl()->redirect($this, self::CMD_TEMPLATES);
    }
}
