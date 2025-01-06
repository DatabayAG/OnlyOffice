<?php

require_once __DIR__ . "/../vendor/autoload.php";
use srag\Plugins\OnlyOffice\Utils\DateFetcher;
use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use srag\DIC\OnlyOffice\DICTrait;
use srag\Plugins\OnlyOffice\StorageService\StorageService;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;

class ilObjOnlyOfficeListGUI extends ilObjectPluginListGUI
{
    use DICTrait;
    use OnlyOfficeTrait;

    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;

    //protected $settings = false;
    //protected $versions = true;
    //protected $available = false;

    public function __construct(int $a_context = self::CONTEXT_REPOSITORY)
    {
        parent::__construct($a_context);
    }

    public function getGuiClass(): string
    {
        return ilObjOnlyOfficeGUI::class;
    }

    public function initCommands(): array
    {

        $this->commands_enabled = true;
        $this->copy_enabled = true;
        $this->cut_enabled = true;
        $this->delete_enabled = true;
        $this->description_enabled = true;
        $this->notice_properties_enabled = true;
        $this->properties_enabled = true;
        $this->info_screen_enabled = true;
        $this->link_enabled = true;

        $this->comments_enabled = false;
        $this->comments_settings_enabled = false;
        $this->expand_enabled = false;
        $this->notes_enabled = false;
        $this->payment_enabled = false;
        $this->preconditions_enabled = false;
        $this->rating_enabled = false;
        $this->rating_categories_enabled = false;
        $this->repository_transfer_enabled = false;
        $this->search_fragment_enabled = false;
        $this->static_link_enabled = false;
        $this->subscribe_enabled = false;
        $this->tags_enabled = false;
        $this->timings_enabled = true;

        $commands = [
            [
                "permission" => "read",
                "cmd" => ilObjOnlyOfficeGUI::getStartCmd(),
                "default" => true
            ],
            [
                // Settings
                "permission" => "edit_permission",
                "cmd" => ilObjOnlyOfficeGUI::CMD_SETTINGS,
                "lang_var" => "settings"
            ],
            [
                // Versions
                "permission" => "read",
                "cmd" => ilObjOnlyOfficeGUI::CMD_SHOW_VERSIONS,
                "lang_var" => "versions"
            ],
        ];

        return $commands;
    }

    public function getProperties(): array
    {
        $storage = new StorageService(
            self::dic()->dic(),
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );
        $file = $storage->getFile($this->obj_id);
        if (is_null($file)) {
            return [];
        }
        $last_version = $storage->getLatestVersion($file->getUuid());
        $props = [];

        if (ilObjOnlyOfficeAccess::_isOffline($this->obj_id)) {
            array_push($props, [
                "alert" => true,
                "property" => self::plugin()->translate("status", ilObjOnlyOfficeGUI::LANG_MODULE_OBJECT),
                "value" => self::plugin()->translate("offline", ilObjOnlyOfficeGUI::LANG_MODULE_OBJECT)
            ]);
        }

        $props[] = [
            "alert" => false,
            'newline' => true,
            "property" => "datatype",
            "value" => $file->getFileType(),
            'propertyNameVisible' => false
        ];

        if (!is_null($last_version)) {
            $props[] = [
                "alert" => false,
                'newline' => true,
                "property" => self::plugin()->translate('last_edit'),
                // ToDo: Evtl. Datumformat noch nach Kundenwunsch anpassen
                "value" => $last_version->getCreatedAt()->get(IL_CAL_FKT_DATE, 'd.m.Y H:i', self::dic()->user()->getTimeZone()),
                'propertyNameVisible' => true
            ];
        }

        if (DateFetcher::editingPeriodIsFetchable($file->getObjId())) {
            $editing_time = DateFetcher::fetchEditingPeriod($file->getObjId());
            $props[] = [
                "alert" => false,
                'newline' => true,
                "property" => self::plugin()->translate('editing_period'),
                "value" => $editing_time,
                'propertyNameVisible' => true
            ];
        }

        return $props;
    }

    public function insertCommands(
        $a_use_asynch = false,
        $a_get_asynch_commands = false,
        $a_asynch_url = "",
        $a_header_actions = false
    ): string {
        return parent::insertCommands(
            $a_use_asynch,
            $a_get_asynch_commands,
            $a_asynch_url,
            $a_header_actions
        );
    }

    public function initType(): void
    {
        $this->setType(ilOnlyOfficePlugin::PLUGIN_ID);
    }

}
