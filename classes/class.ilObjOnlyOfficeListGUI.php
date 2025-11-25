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

use ILIAS\DI\Container;
use ILIAS\Plugin\OnlyOffice\Utils\DateFetcher;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;

class ilObjOnlyOfficeListGUI extends ilObjectPluginListGUI
{
    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;

    //protected $settings = false;
    //protected $versions = true;
    //protected $available = false;

    private Container $dic;

    public function __construct(int $a_context = self::CONTEXT_REPOSITORY)
    {
        parent::__construct($a_context);
        global $DIC;
        $this->dic = $DIC;
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
            $this->dic,
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
            $props[] = [
                "alert" => true,
                "property" => $this->plugin->txt("object_status"),
                "value" => $this->plugin->txt("object_offline")
            ];
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
                "property" => $this->plugin->txt('last_edit'),
                // ToDo: Evtl. Datumformat noch nach Kundenwunsch anpassen
                "value" => $last_version->getCreatedAt()->get(IL_CAL_FKT_DATE, 'd.m.Y H:i', $this->dic->user()->getTimeZone()),
                'propertyNameVisible' => true
            ];
        }

        if (DateFetcher::editingPeriodIsFetchable($file->getObjId())) {
            $editing_time = DateFetcher::fetchEditingPeriod($file->getObjId());
            $props[] = [
                "alert" => false,
                'newline' => true,
                "property" => $this->plugin->txt('editing_period'),
                "value" => $editing_time,
                'propertyNameVisible' => true
            ];
        }

        return $props;
    }

    public function initType(): void
    {
        $this->setType(ilOnlyOfficePlugin::PLUGIN_ID);
    }

}
