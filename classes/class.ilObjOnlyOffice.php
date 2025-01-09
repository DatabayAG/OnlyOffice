<?php

require_once __DIR__ . "/../vendor/autoload.php";

use ILIAS\FileUpload\Exception\IllegalStateException;
use ILIAS\FileUpload\FileUpload;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Refinery\Factory;
use srag\DIC\OnlyOffice\DICTrait;
use srag\Plugins\OnlyOffice\ObjectSettings\ObjectSettings;
use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use srag\Plugins\OnlyOffice\StorageService\StorageService;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;

class ilObjOnlyOffice extends ilObjectPlugin
{
    use DICTrait;
    use OnlyOfficeTrait;

    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    public ObjectSettings $object_settings;

    private ilPlugin $pl;
    private $tpl;
    private Factory $refinery;
    private WrapperFactory $httpWrapper;
    private FileUpload $upload;

    public function __construct(int $a_ref_id = 0)
    {
        global $DIC;

        parent::__construct($a_ref_id);

        $this->refinery = $DIC->refinery();
        $this->httpWrapper = $DIC->http()->wrapper();
        $this->upload = $DIC->upload();

        /** @var $component_factory ilComponentFactory */
        $component_factory = $DIC['component.factory'];
        /** @var $plugin ilOnlyOfficePlugin */
        $this->pl = $component_factory->getPlugin(ilOnlyOfficePlugin::PLUGIN_ID);
        $this->tpl = $DIC["tpl"];
    }

    final public function initType(): void
    {
        $this->setType(ilOnlyOfficePlugin::PLUGIN_ID);
    }

    protected function beforeCreate(): bool
    {
        $editLimited = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->bool(),
                $this->refinery->always(false)
            ])
        );

        if ($editLimited) {
            $startTime = $this->httpWrapper->post()->retrieve(
                ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_START,
                $this->refinery->kindlyTo()->string()
            );

            $endTime = $this->httpWrapper->post()->retrieve(
                ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_END,
                $this->refinery->kindlyTo()->string()
            );

            $start_time = new ilDateTime(date('Ymdhis', strtotime($startTime)), IL_CAL_DATETIME);
            $end_time = new ilDateTime(date('Ymdhis', strtotime($endTime)), IL_CAL_DATETIME);
            if ($start_time->getUnixTime() >= $end_time->getUnixTime()) {
                $this->tpl->setOnScreenMessage('failure', $this->pl->txt("settings_time_greater_than"), true);
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

        $title = $this->httpWrapper->post()->retrieve(
            'title',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $description = $this->httpWrapper->post()->retrieve(
            'desc',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $online = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_ONLINE,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->bool(),
                $this->refinery->always(false)
            ])
        );

        $allow_edit = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_EDIT,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->bool(),
                $this->refinery->always(false)
            ])
        );

        $open_settings = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_OPEN_SETTING,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $limited_period = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->bool(),
                $this->refinery->always(false)
            ])
        );

        $start_time = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_START,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])        );

        $end_time = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_END,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        if ($title === "" && $this->upload->hasUploads()) {
            if (!$this->upload->hasBeenProcessed()) {
                try {
                    $this->upload->process();
                } catch (IllegalStateException $e) {
                }
            }

            if ($this->upload->hasBeenProcessed()) {
                $uploadResults = $this->upload->getResults();
                $file = $uploadResults[array_key_first($uploadResults)];
                $title = explode('.', $file->getName())[0];
            }
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
        $this->object_settings->setDescription($description);
        $this->object_settings->setAllowEdit($allow_edit);
        $this->object_settings->setOnline($online);
        $this->object_settings->setOpen($open_settings);
        $this->object_settings->setLimitedPeriod($limited_period);
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
        $edit_limited = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->bool(),
                $this->refinery->always(false)
            ])
        );

        $start_time = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_START,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])        );

        $end_time = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_EDIT_LIMITED_END,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        if ($edit_limited && !is_null($start_time)) {
            $raw_start_time = new ilDateTime(date('Ymdhis', strtotime($start_time)), IL_CAL_DATETIME);
            $formatted_start_time = new ilDateTime($raw_start_time->get(IL_CAL_DATETIME, 'd.m.Y H:i', ilTimeZone::UTC), IL_CAL_DATETIME);
            $this->object_settings->setStartTime($formatted_start_time->get(IL_CAL_DATETIME));
        }

        if ($edit_limited && !is_null($end_time)) {
            $raw_end_time = new ilDateTime(date('Ymdhis', strtotime($end_time)), IL_CAL_DATETIME);
            $formatted_end_time = new ilDateTime($raw_end_time->get(IL_CAL_DATETIME, 'd.m.Y H:i', ilTimeZone::UTC), IL_CAL_DATETIME);
            $this->object_settings->setEndTime($formatted_end_time->get(IL_CAL_DATETIME));
        }

        $title = $this->httpWrapper->post()->retrieve(
            'title',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $description = $this->httpWrapper->post()->retrieve(
            'desc',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );

        $online = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_ONLINE,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->bool(),
                $this->refinery->always(false)
            ])
        );

        $allow_edit = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_EDIT,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->bool(),
                $this->refinery->always(false)
            ])
        );

        $open_settings = $this->httpWrapper->post()->retrieve(
            ilObjOnlyOfficeGUI::POST_VAR_OPEN_SETTING,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always("")
            ])
        );


        $this->object_settings->setTitle($title);
        $this->object_settings->setDescription($description);
        $this->object_settings->setAllowEdit($allow_edit);
        $this->object_settings->setOpen($open_settings);
        $this->object_settings->setOnline($online);
        $this->object_settings->setLimitedPeriod($edit_limited);
        self::onlyOffice()->objectSettings()->storeObjectSettings($this->object_settings);
    }

    public function doDelete(): void
    {
        if ($this->object_settings !== null) {
            self::onlyOffice()->objectSettings()->deleteObjectSettings($this->object_settings);
        }
        $storage = new StorageService(
            self::dic()->dic(),
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );
        $storage->deleteFile($this->getId());

    }

    protected function doCloneObject(
        $new_obj,
        int $a_target_id,
        ?int $a_copy_id = null
    ): void {
        $new_obj->object_settings = self::onlyOffice()->objectSettings()->cloneObjectSettings($this->object_settings);
        $new_obj->object_settings->setObjId($new_obj->id);
        self::onlyOffice()->objectSettings()->storeObjectSettings($new_obj->object_settings);
        $storage = new StorageService(
            self::dic()->dic(),
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );
        $storage->createClone($new_obj->getId(), $this->getId());
    }

    public function isOnline(): bool
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

    public function getOpen(): string
    {
        return $this->object_settings->getOpen();
    }

    public function isAllowedEdit(): bool
    {
        return $this->object_settings->allowEdit();
    }
}
