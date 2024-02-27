<?php
require_once __DIR__ . "/../vendor/autoload.php";
use srag\DIC\OnlyOffice\DICTrait;
use srag\Plugins\OnlyOffice\ObjectSettings\ObjectSettings;
use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use srag\Plugins\OnlyOffice\StorageService\StorageService;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;

/**
 * Class ilObjOnlyOffice
 * Generated by SrPluginGenerator v1.3.4
 * @author Sophie Pfister <sophie@fluxlabs.ch>
 */
class ilObjOnlyOffice extends ilObjectPlugin
{

    use DICTrait;
    use OnlyOfficeTrait;

    const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    public ObjectSettings $object_settings;

    private ilPlugin $pl;
    private $tpl;

    /**
     * ilObjOnlyOffice constructor
     */
    public function __construct(int $a_ref_id = 0)
    {
        global $DIC;

        parent::__construct($a_ref_id);

        /** @var $component_factory ilComponentFactory */
        $component_factory = $DIC['component.factory'];
        /** @var $plugin ilOnlyOfficePlugin */
        $this->pl  = $component_factory->getPlugin(ilOnlyOfficePlugin::PLUGIN_ID);
        $this->tpl = $DIC["tpl"];
    }

    public final function initType(): void
    {
        $this->setType(ilOnlyOfficePlugin::PLUGIN_ID);
    }

    protected function beforeCreate(): bool
    {
        if ($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED]) {
            $start_time =  new ilDateTime(date('Ymdhis', strtotime($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_START])), IL_CAL_DATETIME);
            $end_time = new ilDateTime(date('Ymdhis', strtotime($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_END])), IL_CAL_DATETIME);
            if ($start_time->getUnixTime() >= $end_time->getUnixTime()) {
                $this->tpl->setOnScreenMessage('failure',$this->pl->txt("settings_time_greater_than"), true);
                self::dic()->ctrl()->redirectByClass("ilRepositoryGUI");
                return false;
            }
        }
        return parent::beforeCreate();
    }

    /**
     * @throws ilDateTimeException
     */
    public function doCreate(bool $clone_mode = false): void
    {
        $this->object_settings = new ObjectSettings();
        $title = $_POST['title'];
        $description = $_POST['desc'];
        $online = $_POST[ilObjOnlyOfficeGUI::POST_VAR_ONLINE];
        $allow_edit = $_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT];
        $open_settings = $_POST[ilObjOnlyOfficeGUI::POST_VAR_OPEN_SETTING];
        $limited_period = $_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED];
        $start_time = $_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_START];
        $end_time = $_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_END];

        if ($title == null) {
            $title = explode('.', $_POST[ilObjOnlyOfficeGUI::POST_VAR_FILE]['name'])[0];
            $_POST['title'] = $title;
        }

        if ($start_time !== "") {
            $raw_start_time = new ilDateTime(date('Ymdhis', strtotime($start_time)), IL_CAL_DATETIME);
            $formatted_start_time = new ilDateTime($raw_start_time->get(IL_CAL_DATETIME, 'd.m.Y H:i', ilTimeZone::UTC), IL_CAL_DATETIME);
            $this->object_settings->setStartTime($formatted_start_time->get(IL_CAL_DATETIME));
        }

        if ($end_time !== "") {
            $raw_end_time = new ilDateTime(date('Ymdhis', strtotime($end_time)), IL_CAL_DATETIME);
            $formatted_end_time = new ilDateTime($raw_end_time->get(IL_CAL_DATETIME, 'd.m.Y H:i', ilTimeZone::UTC), IL_CAL_DATETIME);
            $this->object_settings->setEndTime($formatted_end_time->get(IL_CAL_DATETIME));
        }

        $this->object_settings->setObjId($this->id);
        $this->object_settings->setTitle($title);
        $this->object_settings->setDescription(is_null($description) ? "" : $description);
        $this->object_settings->setAllowEdit(is_null($allow_edit) ? false : $allow_edit);
        $this->object_settings->setOnline(is_null($online) ? false : $online);
        $this->object_settings->setOpen(is_null($open_settings) ? "" : $open_settings);
        $this->object_settings->setLimitedPeriod(is_null($limited_period) ? false : $limited_period);
        self::onlyOffice()->objectSettings()->storeObjectSettings($this->object_settings);
    }

    public function doRead(): void
    {
        $this->object_settings = self::onlyOffice()->objectSettings()->getObjectSettingsById(intval($this->id));
    }

    /**
     * @throws ilDateTimeException
     */
    public function doUpdate(): void
    {
        $start_time = $_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_START];
        $end_time = $_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_END];

        if ($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED] && !is_null($start_time)) {
            $raw_start_time = new ilDateTime(date('Ymdhis', strtotime($start_time)), IL_CAL_DATETIME);
            $formatted_start_time = new ilDateTime($raw_start_time->get(IL_CAL_DATETIME, 'd.m.Y H:i', ilTimeZone::UTC), IL_CAL_DATETIME);
            $this->object_settings->setStartTime($formatted_start_time->get(IL_CAL_DATETIME));
        }

        if ($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED] && !is_null($end_time)) {
            $raw_end_time = new ilDateTime(date('Ymdhis', strtotime($end_time)), IL_CAL_DATETIME);
            $formatted_end_time = new ilDateTime($raw_end_time->get(IL_CAL_DATETIME, 'd.m.Y H:i', ilTimeZone::UTC), IL_CAL_DATETIME);
            $this->object_settings->setEndTime($formatted_end_time->get(IL_CAL_DATETIME));
        }

        $this->object_settings->setTitle($_POST["title"]);
        $this->object_settings->setDescription($_POST["desc"]);
        $this->object_settings->setAllowEdit(boolval($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT]));
        $this->object_settings->setOpen($_POST[ilObjOnlyOfficeGUI::POST_VAR_OPEN_SETTING]);
        $this->object_settings->setOnline(is_null($_POST[ilObjOnlyOfficeGUI::POST_VAR_ONLINE]) ? false : $_POST[ilObjOnlyOfficeGUI::POST_VAR_ONLINE]);
        $this->object_settings->setLimitedPeriod(is_null($_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED]) ? false : $_POST[ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED]);
        self::onlyOffice()->objectSettings()->storeObjectSettings($this->object_settings);
    }

    public function doDelete(): void
    {
        if ($this->object_settings !== null) {
            self::onlyOffice()->objectSettings()->deleteObjectSettings($this->object_settings);
        }
        $storage = new StorageService(self::dic()->dic(), new ilDBFileVersionRepository(), new ilDBFileRepository(),
            new ilDBFileChangeRepository());
        $storage->deleteFile($this->getId());

    }

    protected function doCloneObject($new_obj, int$a_target_id, ?int $a_copy_id = null
    ): void
    {
        $new_obj->object_settings = self::onlyOffice()->objectSettings()->cloneObjectSettings($this->object_settings);
        $new_obj->object_settings->setObjId($new_obj->id);
        self::onlyOffice()->objectSettings()->storeObjectSettings($new_obj->object_settings);
        $storage = new StorageService(self::dic()->dic(), new ilDBFileVersionRepository(), new ilDBFileRepository(),
            new ilDBFileChangeRepository());
        $storage->createClone($new_obj->getId(), $this->getId());
    }

    public function isOnline() : bool
    {
        return $this->object_settings->isOnline();
    }

    public function setOnline(bool $is_online = true): void
    {
        $this->object_settings->setOnline($is_online);
    }

    public function setOpen(string $open = 'ilias'): void
    {
        $this->object_settings->setOpen($open);
    }

    public function getOpen() : string
    {
        return $this->object_settings->getOpen();
    }

    public function isAllowedEdit(): bool
    {
        return $this->object_settings->allowEdit();
    }
}