<?php

namespace srag\Plugins\OnlyOffice\ObjectSettings;

use ilDateTime;
use ilDateTimeInputGUI;
use ilOnlyOfficePlugin;
use ilCheckboxInputGUI;
use ilObjOnlyOffice;
use ilObjOnlyOfficeGUI;
use ilTextAreaInputGUI;
use ilTextInputGUI;
use ilRadioOption;
use ilRadioGroupInputGUI;
use ilUtil;
use srag\CustomInputGUIs\OnlyOffice\PropertyFormGUI\Items\Items;
use srag\CustomInputGUIs\OnlyOffice\PropertyFormGUI\PropertyFormGUI;

/**
 * Class ObjectSettingsFormGUI
 * Generated by SrPluginGenerator v1.3.4
 * @author         Theodor Truffer <thoe@fluxlabs.ch>
 * @author         Sophie Pfister <sophie@fluxlabs.ch>
 */
class ObjectSettingsFormGUI extends PropertyFormGUI
{

    const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    const LANG_MODULE = ilObjOnlyOfficeGUI::LANG_MODULE_SETTINGS;
    /**
     * @var ilObjOnlyOffice
     */
    protected $object;

    /**
     * ObjectSettingsFormGUI constructor
     * @param ilObjOnlyOfficeGUI $parent
     * @param ilObjOnlyOffice    $object
     */
    public function __construct(ilObjOnlyOfficeGUI $parent, ilObjOnlyOffice $object)
    {
        $this->object = $object;

        parent::__construct($parent, $object);
    }

    /**
     * @inheritDoc
     */
    protected function getValue(string $key)
    {
        switch ($key) {
            case "desc":
                return Items::getter($this->object, "long_description");
            case "open_setting":
                return $this->object->getOpen();

            default:
                return Items::getter($this->object, $key);
        }
    }

    /**
     * @inheritDoc
     */
    protected function initCommands()/*: void*/
    {
        $this->addCommandButton(ilObjOnlyOfficeGUI::CMD_SETTINGS_STORE,
            self::plugin()->translate("save", self::LANG_MODULE));

        $this->addCommandButton(ilObjOnlyOfficeGUI::CMD_MANAGE_CONTENTS,
            self::plugin()->translate("cancel", self::LANG_MODULE));
    }

    /**
     * @inheritDoc
     */
    protected function initFields()/*: void*/
    {
        $this->fields = [
            "title" => [
                self::PROPERTY_CLASS => ilTextInputGUI::class
            ],
            "desc" => [
                self::PROPERTY_CLASS => ilTextAreaInputGUI::class
            ],
            ilObjOnlyOfficeGUI::POST_VAR_ONLINE => [
                self::PROPERTY_CLASS => ilCheckboxInputGUI::class
            ],
            ilObjOnlyOfficeGUI::POST_VAR_EDIT => [
                self::PROPERTY_CLASS => ilCheckboxInputGUI::class,
                self::PROPERTY_VALUE => $this->object->isAllowedEdit(),
                self::PROPERTY_SUBITEMS => [
                    ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED => [
                    self::PROPERTY_CLASS => ilCheckboxInputGUI::class,
                    self::PROPERTY_VALUE => $this->object->object_settings->isLimitedPeriod(),
                    self::PROPERTY_SUBITEMS => [
                        ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_START => [
                            self::PROPERTY_CLASS => ilDateTimeInputGUI::class,
                            self::PROPERTY_VALUE => $this->object->object_settings->getStartTime(),
                            self::PROPERTY_REQUIRED => true,
                            "setShowTime" => true
                        ],
                        ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_END => [
                            self::PROPERTY_CLASS => ilDateTimeInputGUI::class,
                            self::PROPERTY_VALUE => $this->object->object_settings->getEndTime(),
                            self::PROPERTY_REQUIRED => true,
                            "setShowTime" => true
                        ]
                    ]
                ]
                ]
            ],
            ilObjOnlyOfficeGUI::POST_VAR_OPEN_SETTING => [
                self::PROPERTY_CLASS => ilRadioGroupInputGUI::class,
                self::PROPERTY_REQUIRED => true,
                self::PROPERTY_SUBITEMS => [
                    "editor" => [
                        self::PROPERTY_CLASS => ilRadioOption::class
                    ],
                    "ilias" => [
                        self::PROPERTY_CLASS => ilRadioOption::class
                    ],
                    "download" => [
                        self::PROPERTY_CLASS => ilRadioOption::class
                    ]
                ]
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function initId()/*: void*/
    {

    }

    /**
     * @inheritDoc
     */
    protected function initTitle()/*: void*/
    {
        $this->setTitle(self::plugin()->translate("settings", self::LANG_MODULE));
    }

    /**
     * @inheritDoc
     */
    protected function storeValue(string $key, $value)/*: void*/
    {
        switch ($key) {
            case "desc":
                Items::setter($this->object, "description", $value);
                break;
            default:
                Items::setter($this->object, $key, $value);
                break;
        }
    }

    /**
     * @inheritDoc
     */
    public function storeForm() : bool
    {
        if (!parent::storeForm()) {
            return false;
        }

        if ($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED]) {
            $start_time = new ilDateTime($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_START], IL_CAL_DATETIME);
            $end_time = new ilDateTime($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_END], IL_CAL_DATETIME);
            if ($start_time->getUnixTime() >= $end_time->getUnixTime()) {
                ilUtil::sendFailure(self::plugin()->translate("time_greater_than", self::LANG_MODULE), true);
                return false;
            }
        }

        $this->object->update();

        return true;
    }
}
