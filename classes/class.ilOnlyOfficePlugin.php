<?php

require_once __DIR__ . "/../vendor/autoload.php";

use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use srag\RemovePluginDataConfirm\OnlyOffice\RepositoryObjectPluginUninstallTrait;
use srag\Plugins\OnlyOffice\StorageService\StorageService;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use srag\Plugins\OnlyOffice\ObjectSettings\ObjectSettings;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileAR;

/**
 *         Sophie Pfister <sophie@fluxlabs.ch>
 */
class ilOnlyOfficePlugin extends ilRepositoryObjectPlugin
{
    use RepositoryObjectPluginUninstallTrait;
    use OnlyOfficeTrait;

    public const PLUGIN_ID = "xono";
    public const PLUGIN_NAME = "OnlyOffice";
    public const PLUGIN_CLASS_NAME = self::class;

    protected static ?ilOnlyOfficePlugin $instance = null;

    public static function getInstance(): self
    {
        if (static::$instance === null) {
            global $DIC;

            /** @var $component_factory ilComponentFactory */
            $component_factory = $DIC['component.factory'];
            /** @var $plugin ilOnlyOfficePlugin */
            $plugin = $component_factory->getPlugin(ilOnlyOfficePlugin::PLUGIN_ID);

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
        $this->db = $DIC->database();
    }

    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

    public function updateLanguages(/*?array*/ $a_lang_keys = null): void
    {
        parent::updateLanguages($a_lang_keys);

        $this->installRemovePluginDataConfirmLanguages();
    }

    protected function deleteData(): void
    {
        self::onlyOffice()->dropTables();
    }

    protected function shouldUseOneUpdateStepOnly(): bool
    {
        return false;
    }

    protected function uninstallCustom(): void
    {
        require_once("./Services/Migration/DBUpdate_3560/classes/class.ilDBUpdateNewObjectType.php");
        $op_id = ilDBUpdateNewObjectType::getCustomRBACOperationId('rep_robj_xono_perm_editFile');
        $type = ilDBUpdateNewObjectType::getObjectTypeId(ilOnlyOfficePlugin::PLUGIN_ID);
        ilDBUpdateNewObjectType::deleteRBACOperation($type, $op_id);

        // Delete all file data
        global $DIC;
        $all_files = FileAR::get();
        $storage = new StorageService(
            $DIC,
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );
        $storage->deleteAll();
        ObjectSettings::truncateDB();

    }

    public static function checkPluginClassNameConst(): string
    {
        return self::PLUGIN_CLASS_NAME;
    }

    public function allowCopy(): bool
    {
        return true;
    }
}
