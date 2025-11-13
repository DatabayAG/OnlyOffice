<?php

namespace ILIAS\Plugin\OnlyOffice\Form;

use ilFileInputGUI;
use ILIAS\DI\Container;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ilObject;
use ilOnlyOfficeConfigGUI;
use ilOnlyOfficePlugin;
use ilPropertyFormGUI;
use ilTextAreaInputGUI;
use ilTextInputGUI;

class TemplateForm extends ilPropertyFormGUI
{

    private ilOnlyOfficePlugin $plugin;
    /**
     * @var Container|mixed
     */
    private mixed $dic;
    private WrapperFactory $httpWrapper;

    public function __construct(bool $edit = false)
    {
        parent::__construct();
        global $DIC;
        $this->dic = $DIC;
        $this->plugin = ilOnlyOfficePlugin::getInstance();
        $this->httpWrapper = $this->dic->http()->wrapper();

        $this->setId("onlyoffice_template_form");
        $this->setTitle($this->plugin->txt("config_configuration"));
        $this->setFormAction($this->ctrl->getFormActionByClass(
            ilOnlyOfficeConfigGUI::class,
            ilOnlyOfficeConfigGUI::CMD_CONFIGURE
        ));
        $this->setTarget("_top");

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

        $this->ctrl->setParameterByClass(ilOnlyOfficeConfigGUI::class, "prevTitle", $ooTarget);
        $this->ctrl->setParameterByClass(ilOnlyOfficeConfigGUI::class, "prevExtension", $ooExtension);
        $this->setFormAction($this->ctrl->getFormActionByClass(ilOnlyOfficeConfigGUI::class));

        // title
        $ti = new ilTextInputGUI($this->plugin->txt("config_table_title"), "title");
        $ti->setSize(min(40, ilObject::TITLE_LENGTH));
        $ti->setMaxLength(ilObject::TITLE_LENGTH);
        $ti->setRequired(true);
        $this->addItem($ti);

        // description
        $ta = new ilTextAreaInputGUI($this->plugin->txt("config_table_description"), "desc");
        $ta->setCols(40);
        $ta->setRows(2);
        $this->addItem($ta);

        // file upload option
        $file_input = new ilFileInputGUI($this->plugin->txt("form_input_file"), "file");
        $file_input->setRequired(!$edit);
        $this->addItem($file_input);

        if ($edit) {
            $this->setTitle($this->plugin->txt("config_edit_template"));
            $this->addCommandButton(ilOnlyOfficeConfigGUI::CMD_SAVE_EDIT_TEMPLATE, $this->plugin->txt("config_save"));
        } else {
            $this->setTitle($this->plugin->txt("config_create_template"));
            $this->addCommandButton(ilOnlyOfficeConfigGUI::CMD_UPDATE_TEMPLATES, $this->plugin->txt("config_save"));
        }

        $this->addCommandButton(ilOnlyOfficeConfigGUI::CMD_TEMPLATES, $this->plugin->txt("settings_cancel"));
    }
}
