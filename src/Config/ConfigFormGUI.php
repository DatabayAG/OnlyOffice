<?php

namespace ILIAS\Plugin\OnlyOffice\Config;

use ilOnlyOfficeConfigGUI;
use ilOnlyOfficePlugin;
use ilTextInputGUI;
use srag\CustomInputGUIs\OnlyOffice\PropertyFormGUI\PropertyFormGUI;

class ConfigFormGUI extends PropertyFormGUI
{
    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;

    public const KEY_ONLYOFFICE_URL = "onlyoffice_url";
    public const KEY_ONLYOFFICE_SECRET = "onlyoffice_secret";
    public const KEY_NUM_VERSIONS = "number_of_versions";

    private \ILIAS\Plugin\OnlyOffice\Repository $repo;

    public function __construct(ilOnlyOfficeConfigGUI $parent)
    {
        $this->repo = \ILIAS\Plugin\OnlyOffice\Repository::getInstance();
        parent::__construct($parent);
    }

    protected function getValue(string $key)
    {
        switch ($key) {
            default:
                return $this->repo->config()->getValue($key);
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
                    $this->repo->config()->setValue($key, 10);
                } else {
                    $this->repo->config()->setValue($key, $value);
                }
                break;
            default:
                $this->repo->config()->setValue($key, $value);
                break;
        }
    }
}
