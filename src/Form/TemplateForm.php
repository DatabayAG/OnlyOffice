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
    private readonly ilOnlyOfficePlugin $plugin;
    /**
     * @var Container|mixed
     */
    private readonly mixed $dic;
    private readonly WrapperFactory $http_wrapper;

    public function __construct(?int $template_id = null)
    {
        parent::__construct();
        global $DIC;
        $this->dic = $DIC;
        $this->plugin = ilOnlyOfficePlugin::getInstance();
        $this->http_wrapper = $this->dic->http()->wrapper();

        $this->setId("onlyoffice_template_form");
        $this->setTitle($this->plugin->txt("config_configuration"));



        $this->setTarget("_top");

        if($template_id) {
            $this->ctrl->setParameterByClass(ilOnlyOfficeConfigGUI::class, "template_id", $template_id);
        }

        $this->setFormAction($this->ctrl->getFormActionByClass(
            ilOnlyOfficeConfigGUI::class,
            ilOnlyOfficeConfigGUI::CMD_TEMPLATES
        ));

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
        $this->addItem($file_input);

        if ($template_id) {
            $this->setTitle($this->plugin->txt("config_edit_template"));
            $this->addCommandButton(ilOnlyOfficeConfigGUI::CMD_SAVE_EDIT_TEMPLATE, $this->plugin->txt("config_save"));
        } else {
            $this->setTitle($this->plugin->txt("config_create_template"));
            $this->addCommandButton(ilOnlyOfficeConfigGUI::CMD_UPDATE_TEMPLATES, $this->plugin->txt("config_save"));
        }

        $this->addCommandButton(ilOnlyOfficeConfigGUI::CMD_TEMPLATES, $this->plugin->txt("settings_cancel"));
    }
}
