<?php

namespace srag\Plugins\OnlyOffice\Config;

use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use ilOnlyOfficeConfigGUI;
use ilOnlyOfficePlugin;
use ilTextInputGUI;
use srag\CustomInputGUIs\OnlyOffice\PropertyFormGUI\PropertyFormGUI;

class ConfigFormGUI extends PropertyFormGUI
{
    use OnlyOfficeTrait;

    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;

    public const KEY_ONLYOFFICE_URL = "onlyoffice_url";
    public const KEY_ONLYOFFICE_SECRET = "onlyoffice_secret";
    public const KEY_NUM_VERSIONS = "number_of_versions";

    public const LANG_MODULE = ilOnlyOfficeConfigGUI::LANG_MODULE;

    public function __construct(ilOnlyOfficeConfigGUI $parent)
    {
        parent::__construct($parent);
    }

    protected function getValue(string $key)
    {
        switch ($key) {
            default:
                return self::onlyOffice()->config()->getValue($key);
        }
    }

    protected function initCommands(): void
    {
        $this->addCommandButton(ilOnlyOfficeConfigGUI::CMD_UPDATE_CONFIGURE, $this->txt("save"));
    }

    protected function initFields(): void
    {
        $this->fields = [
            self::KEY_ONLYOFFICE_URL => [
                self::PROPERTY_CLASS => ilTextInputGUI::class,
                self::PROPERTY_REQUIRED => true
            ],
            self::KEY_ONLYOFFICE_SECRET => [
                self::PROPERTY_CLASS => \ilPasswordInputGUI::class,
                self::PROPERTY_REQUIRED => true
            ],
            self::KEY_NUM_VERSIONS => [
                self::PROPERTY_CLASS => \ilNumberInputGUI::class
            ]
        ];
    }

    protected function initId(): void
    {

    }

    protected function initTitle(): void
    {
        $this->setTitle($this->txt("configuration"));
    }

    protected function storeValue(string $key, $value): void
    {
        switch ($key) {
            // If less than 1 version should be loaded from storage, a default value (10) is stored
            case self::KEY_NUM_VERSIONS:
                if ($value < 1) {
                    self::onlyOffice()->config()->setValue($key, 10);
                } else {
                    self::onlyOffice()->config()->setValue($key, $value);
                }
                break;
            default:
                self::onlyOffice()->config()->setValue($key, $value);
                break;
        }
    }
}
