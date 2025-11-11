<?php

require_once __DIR__ . "/../vendor/autoload.php";

use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\ObjectSettings\ObjectSettings;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileAR;

/**
 *         Sophie Pfister <sophie@fluxlabs.ch>
 */
class ilOnlyOfficePlugin extends ilRepositoryObjectPlugin
{

    public const PLUGIN_ID = "xono";
    public const PLUGIN_NAME = "OnlyOffice";
    public const PLUGIN_CLASS_NAME = self::class;

    protected static ?ilOnlyOfficePlugin $instance = null;
    private Repository $repo;

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
        $this->repo = Repository::getInstance();
        $this->db = $DIC->database();
    }

    public function getPluginName(): string
    {
        return self::PLUGIN_NAME;
    }

    protected function deleteData(): void
    {
        $this->repo->dropTables();
    }

    protected function shouldUseOneUpdateStepOnly(): bool
    {
        return false;
    }

    protected function beforeUninstallCustom(): bool
    {
        require_once(ILIAS_ABSOLUTE_PATH . "/components/ILIAS/Migration/DBUpdate_3560/classes/class.ilDBUpdateNewObjectType.php");
        $op_id = ilDBUpdateNewObjectType::getCustomRBACOperationId('rep_robj_xono_perm_editFile');
        $type = ilDBUpdateNewObjectType::getObjectTypeId(ilOnlyOfficePlugin::PLUGIN_ID);
        ilDBUpdateNewObjectType::deleteRBACOperation($type, $op_id);

        return parent::beforeUninstallCustom();
    }

    protected function uninstallCustom(): void
    {
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
