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

use ILIAS\Plugin\OnlyOffice\Enum\PluginAsset;
use ILIAS\Plugin\OnlyOffice\Repository\FileChangeRepository;
use ILIAS\Plugin\OnlyOffice\Repository\FileVersionRepository;
use ILIAS\Plugin\OnlyOffice\Setup\DBUpdateSteps;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileAR;

class ilOnlyOfficePlugin extends ilRepositoryObjectPlugin
{
    public const string ID = "xono";

    protected static ?ilOnlyOfficePlugin $instance = null;
    public ilSetting $settings;

    public static function getInstance(): self
    {
        if (static::$instance === null) {
            global $DIC;

            /** @var $component_factory ilComponentFactory */
            $component_factory = $DIC['component.factory'];
            /** @var $plugin ilOnlyOfficePlugin */
            $plugin = $component_factory->getPlugin(self::ID);

            static::$instance = $plugin;
        }

        return self::$instance;
    }

    public function __construct(
        ilDBInterface $db,
        ilComponentRepositoryWrite $component_repository,
        string $id
    ) {
        global $DIC;
        parent::__construct($db, $component_repository, $id);
        $this->settings = new ilSetting(self::ID . "_config");

        $this->db = $DIC->database();
    }

    protected function shouldUseOneUpdateStepOnly(): bool
    {
        return false;
    }


    public function install(): void
    {
        (new DBUpdateSteps())->install($this->db);
        parent::install();
    }

    public function update(): bool
    {
        //DbUpdateSteps should be execuded before the parent update so potential exceptions result in no il_plugin db changes
        (new DBUpdateSteps())->install($this->db);

        return parent::update();
    }

    protected function uninstallCustom(): void
    {
        (new DBUpdateSteps())->uninstall($this->db);
        // Delete all file data
        global $DIC;
        $all_files = FileAR::get();
        $storage = new StorageService(
            $DIC,
            new FileVersionRepository(),
            new ilDBFileRepository(),
            new FileChangeRepository()
        );
        $storage->deleteAll();
    }

    protected function beforeUninstallCustom(): bool
    {
        require_once(ILIAS_ABSOLUTE_PATH . "/components/ILIAS/Migration/DBUpdate_3560/classes/class.ilDBUpdateNewObjectType.php");
        $op_id = ilDBUpdateNewObjectType::getCustomRBACOperationId('rep_robj_xono_perm_editFile');
        $type = ilDBUpdateNewObjectType::getObjectTypeId(self::ID);
        ilDBUpdateNewObjectType::deleteRBACOperation((string) $type, $op_id);

        return parent::beforeUninstallCustom();
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

    public function assetsFile(PluginAsset $assetType, string $file, bool $relative = true): string
    {
        $basePath = $relative ? $this->getRelativeDirectory() : $this->getDirectory();
        return $basePath . "/assets/" . $assetType->value . "/" . $file;
    }
}
