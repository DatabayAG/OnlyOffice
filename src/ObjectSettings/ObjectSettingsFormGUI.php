<?php

namespace srag\Plugins\OnlyOffice\ObjectSettings;

use ilComponentFactory;
use ilDateTime;
use ilDateTimeInputGUI;
use ilOnlyOfficePlugin;
use ilCheckboxInputGUI;
use ilObjOnlyOffice;
use ilObjOnlyOfficeGUI;
use ilPlugin;
use ilTextAreaInputGUI;
use ilTextInputGUI;
use ilRadioOption;
use ilRadioGroupInputGUI;
use srag\CustomInputGUIs\OnlyOffice\PropertyFormGUI\Items\Items;
use srag\CustomInputGUIs\OnlyOffice\PropertyFormGUI\PropertyFormGUI;

class ObjectSettingsFormGUI extends PropertyFormGUI
{
    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    public const LANG_MODULE = ilObjOnlyOfficeGUI::LANG_MODULE_SETTINGS;
    protected ilObjOnlyOffice $object;
    private ilPlugin $pl;

    public function __construct(ilObjOnlyOfficeGUI $parent, ilObjOnlyOffice $object)
    {
        global $DIC;
        $this->object = $object;

        /** @var $component_factory ilComponentFactory */
        $component_factory = $DIC['component.factory'];
        /** @var $plugin ilOnlyOfficePlugin */
        $this->pl = $component_factory->getPlugin(ilOnlyOfficePlugin::PLUGIN_ID);

        parent::__construct($parent);
    }
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

    protected function initCommands(): void
    {
        $this->addCommandButton(
            ilObjOnlyOfficeGUI::CMD_SETTINGS_STORE,
            self::plugin()->translate("save", self::LANG_MODULE)
        );

        $this->addCommandButton(
            ilObjOnlyOfficeGUI::CMD_MANAGE_CONTENTS,
            self::plugin()->translate("cancel", self::LANG_MODULE)
        );
    }

    protected function initFields(): void
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

    protected function initId(): void
    {

    }

    protected function initTitle(): void
    {
        $this->setTitle(self::plugin()->translate("settings", self::LANG_MODULE));
    }

    protected function storeValue(string $key, $value): void
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

    public function storeForm(): bool
    {
        if (!parent::storeForm()) {
            return false;
        }

        if ($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED]) {

            $start_time = new ilDateTime(date('Ymdhis', strtotime($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_START])), IL_CAL_DATETIME);
            $end_time = new ilDateTime(date('Ymdhis', strtotime($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_END])), IL_CAL_DATETIME);

            if ($start_time->getUnixTime() >= $end_time->getUnixTime()) {
                global $DIC;
                $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->pl->txt("settings_time_greater_than"), true);
                return false;
            }
        }

        $this->object->update();

        return true;
    }
}
