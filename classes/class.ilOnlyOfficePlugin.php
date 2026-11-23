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

require_once __DIR__ . "/../vendor/autoload.php";

use ILIAS\DI\Container;
use ILIAS\Plugin\OnlyOffice\Enum\PluginAsset;
use ILIAS\Plugin\OnlyOffice\Setup\DBUpdateSteps;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;

class ilOnlyOfficePlugin extends ilRepositoryObjectPlugin
{
    public const string ID = "xono";

    private static ?self $instance = null;
    public Container $dic;
    public ilSetting $settings;
    private ?StorageService $storage_service = null;

    public function __construct(
        ilDBInterface $db,
        ilComponentRepositoryWrite $component_repository,
        string $id
    ) {
        global $DIC;
        $this->dic = $DIC;
        $this->settings = new ilSetting(self::ID . "_config");
        parent::__construct($db, $component_repository, $id);
    }

    public static function getInstance(): self
    {
        if (self::$instance) {
            return self::$instance;
        }

        global $DIC;

        /**
         * @var ilComponentFactory $component_factory
         */
        $component_factory = $DIC["component.factory"];
        self::$instance = $component_factory->getPlugin(self::ID);
        return self::$instance;
    }

    public function getStorageService(): StorageService
    {
        if (!$this->storage_service) {
            $this->storage_service = new StorageService();
        }
        return $this->storage_service;
    }


    public function install(): void
    {
        (new DBUpdateSteps())->install($this->db);
        parent::install();
    }

    public function update(): bool
    {
        //DbUpdateSteps should be executed before the parent update so potential exceptions result in no il_plugin db changes
        (new DBUpdateSteps())->install($this->db);

        return parent::update();
    }

    protected function uninstallCustom(): void
    {
        $this->settings->deleteAll();
        $this->getStorageService()->deleteAll();

        (new DBUpdateSteps())->uninstall($this->db);
    }

    public function allowCopy(): bool
    {
        return true;
    }

    public static function _getIcon(string $a_type): string
    {
        // ILIAS Core plugin path fix (Mantis #45866)
        return str_replace(
            ILIAS_ABSOLUTE_PATH . "/public/",
            "",
            realpath(parent::_getIcon($a_type))
        );
    }

    public function assetsFile(PluginAsset $asset_type, string $file, bool $relative = true): string
    {
        $base_path = $relative ? $this->getRelativeDirectory() : $this->getDirectory();
        return $base_path . "/assets/" . $asset_type->value . "/" . $file;
    }
}
