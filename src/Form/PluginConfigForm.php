<?php

namespace ILIAS\Plugin\OnlyOffice\Form;

use ilNumberInputGUI;
use ilOnlyOfficeConfigGUI;
use ilOnlyOfficePlugin;
use ilPasswordInputGUI;
use ilPropertyFormGUI;
use ilTextInputGUI;

class PluginConfigForm extends ilPropertyFormGUI
{
    public const KEY_ONLYOFFICE_URL = "onlyoffice_url";
    public const KEY_ONLYOFFICE_SECRET = "onlyoffice_secret";
    public const KEY_NUM_VERSIONS = "number_of_versions";

    private ilOnlyOfficePlugin $plugin;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = ilOnlyOfficePlugin::getInstance();

        $this->setId("onlyoffice_config_form");
        $this->setTitle($this->plugin->txt("config_configuration"));
        $this->setFormAction($this->ctrl->getFormActionByClass(
            ilOnlyOfficeConfigGUI::class,
            ilOnlyOfficeConfigGUI::CMD_CONFIGURE
        ));

        $url = new ilTextInputGUI(
            $this->plugin->txt("config_onlyoffice_url"),
            self::KEY_ONLYOFFICE_URL
        );
        $url->setRequired(true);
        $this->addItem($url);

        $secret = new ilPasswordInputGUI(
            $this->plugin->txt("config_onlyoffice_secret"),
            self::KEY_ONLYOFFICE_URL
        );
        $secret->setRequired(true);
        $secret->setRetype(false);
        $this->addItem($secret);

        $versions = new ilNumberInputGUI(
            $this->plugin->txt("config_number_of_versions"),
            self::KEY_NUM_VERSIONS
        );
        $versions->setRequired(true);
        $this->addItem($versions);

        $this->addCommandButton(
            ilOnlyOfficeConfigGUI::CMD_UPDATE_CONFIGURE,
            $this->plugin->txt("config_save")
        );
    }
}
