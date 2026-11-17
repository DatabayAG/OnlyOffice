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
use ILIAS\FileUpload\DTO\ProcessingStatus;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\FileUpload\FileUpload;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Enum\PluginAsset;
use ILIAS\Plugin\OnlyOffice\Form\PluginConfigForm;
use ILIAS\Plugin\OnlyOffice\Form\TemplateForm;
use ILIAS\Plugin\OnlyOffice\Model\FileTemplate;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\Refinery\Factory;
use ILIAS\UI\Renderer;

/**
 * @ilCtrl_IsCalledBy  ilOnlyOfficeConfigGUI: ilObjComponentSettingsGUI
 */
class ilOnlyOfficeConfigGUI extends ilPluginConfigGUI
{
    public const string CMD_SHOW_CONFIGURATION = "showConfiguration";
    public const string CMD_SHOW_TEMPLATES = "showTemplates";
    public const string CMD_SHOW_CREATE_TEMPLATE = "showCreateTemplate";
    public const string CMD_SHOW_EDIT_TEMPLATE = "showEditTemplate";

    public const string CMD_SHOW_CONFIRM_DELETE = "showConfirmDelete";



    public const string CMD_SAVE_CONFIGURATION = "saveConfiguration";
    public const string CMD_SAVE_EDIT_TEMPLATE = "saveEditTemplate";
    public const string CMD_SAVE_TEMPLATE = "saveTemplate";
    public const string CMD_DELETE_TEMPLATE = "deleteTemplate";

    public const string TAB_CONFIGURATION = "configuration";
    public const string TAB_SUB_CONFIGURATION = "subConfiguration";
    public const string TAB_SUB_TEMPLATES = "templates";

    private readonly ilGlobalTemplateInterface $tpl;
    private readonly Factory $refinery;
    private readonly WrapperFactory $http_wrapper;
    private Container $dic;
    private readonly ilOnlyOfficePlugin $plugin;
    private readonly StorageService $storage_service;
    private ilToolbarGUI $toolbar;
    private ilCtrlInterface $ctrl;
    private ilTabsGUI $tabs;
    private ilLocatorGUI $locator;
    private ILIAS\UI\Factory $ui_factory;
    private Renderer $ui_renderer;
    private FileUpload $upload;
    private ilLanguage $lng;

    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;

        /** @var $plugin ilOnlyOfficePlugin */
        $this->plugin = ilOnlyOfficePlugin::getInstance();
        $this->refinery = $this->dic->refinery();
        $this->http_wrapper = $this->dic->http()->wrapper();
        $this->tpl = $this->dic->ui()->mainTemplate();
        $this->toolbar = $this->dic->toolbar();
        $this->ctrl = $this->dic->ctrl();
        $this->tabs = $this->dic->tabs();
        $this->locator = $this->dic["ilLocator"];
        $this->ui_factory = $this->dic->ui()->factory();
        $this->ui_renderer = $this->dic->ui()->renderer();
        $this->upload = $this->dic->upload();
        $this->lng = $this->dic->language();

        $this->storage_service = $this->plugin->getStorageService();
    }

    public function performCommand(string $cmd): void
    {
        $this->setTabs();

        $next_class = $this->ctrl->getNextClass($this);

        switch (strtolower($next_class)) {
            default:
                $cmd = $cmd === "configure" ? $this->getDefaultCommand() : $cmd;

                switch ($cmd) {
                    case self::CMD_SHOW_CONFIGURATION:
                    case self::CMD_SHOW_TEMPLATES:
                    case self::CMD_SHOW_CREATE_TEMPLATE:
                    case self::CMD_SAVE_EDIT_TEMPLATE:
                    case self::CMD_SHOW_EDIT_TEMPLATE:
                    case self::CMD_DELETE_TEMPLATE:
                    case self::CMD_SAVE_CONFIGURATION:
                    case self::CMD_SAVE_TEMPLATE:
                    case self::CMD_SHOW_CONFIRM_DELETE:
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

    protected function getDefaultCommand(): string
    {
        return self::CMD_SHOW_CONFIGURATION;
    }

    protected function setTabs(): void
    {
        $this->tabs->addTab(self::TAB_CONFIGURATION, $this->plugin->txt("config_configuration"), $this->ctrl
            ->getLinkTargetByClass(self::class, self::CMD_SHOW_CONFIGURATION));

        $this->tabs->addSubTab(self::TAB_SUB_CONFIGURATION, $this->plugin->txt("config_tab_general"), $this->ctrl
            ->getLinkTargetByClass(self::class, self::CMD_SHOW_CONFIGURATION));

        $this->tabs->addSubTab(self::TAB_SUB_TEMPLATES, $this->plugin->txt("config_tab_templates"), $this->ctrl
            ->getLinkTargetByClass(self::class, self::CMD_SHOW_TEMPLATES));


        $this->locator->addItem($this->plugin->getPluginName(), $this->ctrl->getLinkTarget($this, self::CMD_SHOW_CONFIGURATION));
    }

    protected function showConfiguration(?PluginConfigForm $form = null): void
    {
        $this->tabs->activateTab(self::TAB_CONFIGURATION);
        $this->tabs->activateSubTab(self::TAB_SUB_CONFIGURATION);

        if (!$form) {
            $form = new PluginConfigForm();
            $form->setValuesByArray([
                PluginConfigForm::KEY_ONLYOFFICE_URL => $this->plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_URL),
                PluginConfigForm::KEY_ONLYOFFICE_SECRET => $this->plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_SECRET),
                PluginConfigForm::KEY_NUM_VERSIONS => (int) $this->plugin->settings->get(PluginConfigForm::KEY_NUM_VERSIONS, "10"),
            ], true);
        }

        $this->tpl->setContent($form->getHTML());
    }

    protected function showTemplates(): void
    {
        $this->tabs->activateTab(self::TAB_CONFIGURATION);
        $this->tabs->activateSubTab(self::TAB_SUB_TEMPLATES);

        $this->toolbar->addButton(
            $this->plugin->txt("config_create_template"),
            $this->ctrl->getLinkTargetByClass(self::class, self::CMD_SHOW_CREATE_TEMPLATE)
        );

        $tpl = new ilTemplate($this->plugin->assetsFile(PluginAsset::Templates, "tpl.config_create_template.html", false), true, true);

        $text_templates = $this->storage_service->fetchTemplates(FileCreationType::TEXT);
        $table_templates = $this->storage_service->fetchTemplates(FileCreationType::TABLE);
        $presentation_templates = $this->storage_service->fetchTemplates(FileCreationType::PRESENTATION);
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

            $this->ctrl->setParameterByClass(self::class, "template_id", $template->getId());
            $actions = $this->ui_factory->dropdown()->standard([
                $this->ui_factory->link()->standard(
                    $this->plugin->txt("config_table_edit"),
                    $this->ctrl->getLinkTargetByClass(
                        self::class,
                        self::CMD_SHOW_EDIT_TEMPLATE
                    )
                ),
                $this->ui_factory->link()->standard(
                    $this->plugin->txt("config_table_delete"),
                    $this->ctrl->getLinkTargetByClass(
                        self::class,
                        self::CMD_SHOW_CONFIRM_DELETE
                    )
                )
            ])->withLabel($this->plugin->txt("config_table_options"));

            $tpl->setVariable('SETTINGS', $this->ui_renderer->render($actions));
            $tpl->parseCurrentBlock();
        }

        $content = $tpl->get();
        $this->tpl->setContent($content);
    }

    protected function showCreateTemplate(?TemplateForm $form = null): void
    {
        $this->tabs->activateTab(self::TAB_CONFIGURATION);
        $this->tabs->activateSubTab(self::TAB_SUB_TEMPLATES);

        if (!$form) {
            $form = new TemplateForm();
        }
        $this->tpl->setContent($form->getHTML());
    }

    protected function saveConfiguration(): void
    {
        $this->tabs->activateTab(self::TAB_CONFIGURATION);

        $form = new PluginConfigForm($this);

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showConfiguration($form);
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
        $this->ctrl->redirect($this, self::CMD_SHOW_CONFIGURATION);
    }

    protected function saveTemplate(): void
    {
        $form = new TemplateForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showCreateTemplate($form);
            return;
        }

        if (!$this->upload->hasBeenProcessed()) {
            $this->upload->process();
        }

        $results = $this->upload->getResults();
        $result = end($results);

        $file_service_settings = $this->dic->fileServiceSettings();
        $extension = pathinfo($result->getName(), PATHINFO_EXTENSION);

        // Return if file extension not whitelisted by ILIAS instance
        if (!in_array($extension, $file_service_settings->getWhiteListedSuffixes(), true) || in_array($extension, $file_service_settings->getBlackListedSuffixes(), true)) {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("config_template_invalid_extension"), true);
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            return;
        }

        $title = $this->http_wrapper->post()->retrieve(
            "title",
            $this->refinery->kindlyTo()->string()
        );

        $description = $this->http_wrapper->post()->retrieve(
            "desc",
            $this->refinery->kindlyTo()->string()
        );

        $file_template = $this->storage_service->createFileTemplate($result, $title, $description);

        // Return if file extension not recognized by OnlyOffice
        if (!$file_template) {
            $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("config_template_unrecognised_extension"), true);
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            return;
        }

        $this->tpl->setOnScreenMessage('success', $this->plugin->txt("config_template_saved"), true);
        $this->ctrl->redirect($this, self::CMD_SHOW_TEMPLATES);
    }

    protected function showEditTemplate(): void
    {
        $this->tabs->activateTab(self::TAB_CONFIGURATION);
        $this->tabs->activateSubTab(self::TAB_SUB_TEMPLATES);

        $template_id = $this->http_wrapper->query()->retrieve(
            "template_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if (!$template_id) {
            $this->ctrl->redirect($this, self::CMD_SHOW_TEMPLATES);
        }

        $template = $this->storage_service->fetchTemplate($template_id);

        if (!$template) {
            $this->ctrl->redirect($this, self::CMD_SHOW_TEMPLATES);
        }


        $form = new TemplateForm($template->getId());
        $form->setValuesByArray([
            "title" => $template->getTitle(),
            "desc" => $template->getDescription(),
        ]);
        $this->tpl->setContent($form->getHTML());
    }

    protected function saveEditTemplate(): void
    {
        $template_id = $this->http_wrapper->query()->retrieve(
            "template_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if (!$template_id) {
            $this->ctrl->redirect($this, self::CMD_SHOW_TEMPLATES);
        }

        $template = $this->storage_service->fetchTemplate($template_id);

        if (!$template) {
            $this->ctrl->redirect($this, self::CMD_SHOW_TEMPLATES);
        }

        $form = new TemplateForm($template->getId());

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            return;
        }

        if (!$this->upload->hasBeenProcessed()) {
            $this->upload->process();
        }

        $results = $this->upload->getResults();
        /**
         * @var UploadResult $upload_result
         */
        $upload_result = end($results);
        if ($upload_result->getStatus()->getCode() !== ProcessingStatus::OK) {
            $upload_result = null;
        }

        $template
            ->setTitle($form->getInput("title"))
            ->setDescription($form->getInput("desc"));

        if ($upload_result) {
            // Return if file extension not whitelisted by ILIAS instance
            if (!ilFileUtils::getValidFilename($upload_result->getName())) {
                $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("config_template_invalid_extension"), true);

                $this->ctrl->setParameterByClass(self::class, "template_id", $template->getId());
                $this->ctrl->redirectByClass(self::class, self::CMD_SHOW_EDIT_TEMPLATE);
                return;
            }

            $extension = pathinfo($upload_result->getName(), PATHINFO_EXTENSION);
            $file_type = FileCreationType::determineDocType($extension);

            // Return if file extension not recognized by OnlyOffice
            if ($file_type === FileCreationType::NONE) {
                $this->tpl->setOnScreenMessage('failure', $this->plugin->txt("config_template_unrecognised_extension"), true);

                $this->ctrl->setParameterByClass(self::class, "template_id", $template->getId());
                $this->ctrl->redirectByClass(self::class, self::CMD_SHOW_EDIT_TEMPLATE);
                return;
            }

            $template
                ->setType($file_type)
                ->setExtension($extension);
        }

        $this->storage_service->modifyTemplate($template, $upload_result);

        $this->tpl->setOnScreenMessage('success', $this->plugin->txt("config_template_edited"), true);

        $this->ctrl->redirect($this, self::CMD_SHOW_TEMPLATES);
    }

    public function showConfirmDelete(): void
    {
        $conf = new ilConfirmationGUI();
        $conf->setFormAction($this->ctrl->getFormAction($this));
        $conf->setHeaderText($this->plugin->txt('config_template_delete'));

        $template_id = $this->http_wrapper->query()->retrieve(
            "template_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if (!$template_id) {
            $this->ctrl->redirect($this, self::CMD_SHOW_TEMPLATES);
        }

        $template = $this->storage_service->fetchTemplate($template_id);

        if (!$template) {
            $this->ctrl->redirect($this, self::CMD_SHOW_TEMPLATES);
        }

        $conf->addItem("template_id", (string) $template_id, sprintf(
            "| %s | %s | %s | %s |",
            $template->getId(),
            $template->getTitle(),
            $this->plugin->txt("form_input_create_file_" . $template->getType()->value),
            $template->getExtension(),
        ));

        $conf->setConfirm($this->lng->txt('delete'), self::CMD_DELETE_TEMPLATE);
        $conf->setCancel($this->lng->txt('cancel'), self::CMD_SHOW_TEMPLATES);

        $this->tpl->setContent($conf->getHTML());
    }

    protected function deleteTemplate(): void
    {
        $template_id = $this->http_wrapper->post()->retrieve(
            "template_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if (!$template_id) {
            $this->ctrl->redirect($this, self::CMD_SHOW_TEMPLATES);
        }

        $success = $this->storage_service->deleteFileTemplate($template_id);

        if ($success) {
            $this->tpl->setOnScreenMessage('success', $this->plugin->txt("config_template_deleted"), true);
        }

        $this->ctrl->redirect($this, self::CMD_SHOW_TEMPLATES);
    }
}
