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
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\OnlyOffice\Enum\PluginAsset;
use ILIAS\Plugin\OnlyOffice\Form\PluginConfigForm;
use ILIAS\Plugin\OnlyOffice\Repository\ObjectSettingsRepository;
use ILIAS\Plugin\OnlyOffice\Utils\UiUtil;
use ILIAS\Refinery\Factory;
use ILIAS\Plugin\OnlyOffice\Utils\DateFetcher;
use ILIAS\ResourceStorage\Services;

class xonoContentGUI extends xonoAbstractGUI
{
    public const string BASE_URL = ILIAS_HTTP_PATH;
    public const string CMD_STANDARD = 'showVersions';
    public const string CMD_SHOW_VERSIONS = 'showVersions';
    public const string CMD_DOWNLOAD = 'downloadFileVersion';
    public const string CMD_EDIT = xonoEditorGUI::CMD_EDIT;

    protected ilOnlyOfficePlugin $plugin;
    private readonly ilGlobalTemplateInterface $tpl;
    private readonly Factory $refinery;
    private readonly WrapperFactory $http_wrapper;
    private readonly ObjectSettingsRepository $object_settings_repo;
    private Services $resource_storage;
    private UiUtil $ui_util;

    public function __construct(
        Container $dic,
        ilOnlyOfficePlugin $plugin,
        protected int $file_id
    ) {

        $this->refinery = $dic->refinery();
        $this->http_wrapper = $dic->http()->wrapper();

        parent::__construct($dic, $plugin);
        $this->tpl = $dic->ui()->mainTemplate();
        $this->resource_storage = $dic->resourceStorage();

        $this->object_settings_repo = new ObjectSettingsRepository($dic->database());
        $this->ui_util = new UiUtil($dic);
    }

    final public function getType(): string
    {
        return ilOnlyOfficePlugin::ID;
    }

    public function executeCommand(): void
    {
        $this->dic->tabs()->activateTab(ilObjOnlyOfficeGUI::TAB_SHOW_CONTENTS);

        $this->dic->help()->setScreenIdComponent(ilOnlyOfficePlugin::ID);
        $next_class = $this->dic->ctrl()->getNextClass($this);
        $cmd = $this->dic->ctrl()->getCmd(self::CMD_STANDARD);

        if (strtolower($next_class) === strtolower(xonoEditorGUI::class)) {
            $xono_editor = new xonoEditorGUI($this->dic, $this->plugin, $this->file_id);
            $this->dic->ctrl()->forwardCommand($xono_editor);
        } else {
            match ($cmd) {
                self::CMD_EDIT => $this->dic->ctrl()->redirectByClass(xonoEditorGUI::class, xonoEditorGUI::CMD_EDIT),
                default => $this->{$cmd}(),
            };
        }
    }

    /**
     * Fetches the information about all versions of a file from the database
     * Renders the GUI for content
     */
    protected function showVersions(): void
    {
        $storage_service = $this->plugin->getStorageService();

        $file_versions = $storage_service->getAllVersions($this->file_id);
        $file = $storage_service->getFile($this->file_id);
        if (is_null($file)) {
            $this->dic->ui()->mainTemplate()->setContent("");
            return;
        }

        $this->ui_util->sendInfo($this->plugin->txt("xono_reload_info"));

        $tpl = new ilTemplate($this->plugin->assetsFile(PluginAsset::Templates, "tpl.file_history.html", false), true, true);
        $tpl->setVariable('VERSION', $this->plugin->txt('xono_version'));
        $tpl->setVariable('CREATED', $this->plugin->txt('xono_date'));
        $tpl->setVariable('EDITOR', $this->plugin->txt('xono_editor'));
        $tpl->setVariable('DOWNLOAD', $this->plugin->txt('xono_download'));
        $tpl->setVariable('FORWARD', $this->buttonTarget());
        $tpl->setVariable('BUTTON', $this->buttonName());

        $limit = (int) $this->plugin->settings->get(PluginConfigForm::KEY_NUM_VERSIONS, "10");
        $file_versions_added = 0;
        foreach ($file_versions as $file_version) {
            if ($file_versions_added >= $limit) {
                break;
            }
            $user = new ilObjUser($file_version->getUserId());
            $tpl->setVariable('TABLE_ROW_VERSION', $file_version->getVersion());
            $tpl->setVariable(
                'TABLE_ROW_CREATED_AT',
                $file_version->getCreatedAt()->get(
                    IL_CAL_FKT_DATE,
                    'd.m.Y H:i',
                    $this->dic->user()->getTimeZone()
                )
            );
            $tpl->setVariable('TABLE_ROW_USER', $user->getPublicName());
            $this->dic->ctrl()->setParameter($this, "version", $file_version->getVersion());
            $tpl->setVariable('TABLE_ROW_DOWNLOAD_URL', $this->dic->ctrl()->getLinkTarget($this, self::CMD_DOWNLOAD));
            $file_versions_added++;
            $tpl->setCurrentBlock("table_row");
            $tpl->parseCurrentBlock("table_row");
        }

        if (DateFetcher::editingPeriodIsFetchable($file->getObjId())) {
            $editing_period = DateFetcher::fetchEditingPeriod($file->getObjId());
            $tpl->setVariable('EDITING_PERIOD', sprintf("<p>%s: %s</p>", $this->plugin->txt('editing_period'), $editing_period));
        }

        $content = $tpl->get();
        $this->dic->ui()->mainTemplate()->setContent($content);
    }

    /**
     * Delivers a file version for download
     */
    protected function downloadFileVersion(): never
    {
        $storage_service = $this->plugin->getStorageService();

        $requested_version = $this->http_wrapper->query()->retrieve(
            "version",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if ($requested_version === null) {
            $this->dic->ctrl()->redirectByClass(self::class, self::CMD_SHOW_VERSIONS);
        }

        $file_version = null;
        foreach ($storage_service->getAllVersions($this->file_id) as $version) {
            if ($version->getVersion() === $requested_version) {
                $file_version = $version;
                break;
            }
        }

        $file = $storage_service->getFile($this->file_id);

        if (!$file_version || !$file || !$file_version->getRid()) {
            $this->ui_util->sendFailure($this->plugin->txt("file.not_found"));
            $this->dic->ctrl()->redirectByClass(self::class, self::CMD_SHOW_VERSIONS);
        }

        $download = $this->resource_storage->consume()->download($file_version->getRid());
        $ext = pathinfo($file->getTitle(), PATHINFO_EXTENSION);
        $file_name = rtrim($file->getTitle(), '.' . $ext);

        try {
            $download->overrideFileName(
                "{$file_name}_V{$file_version->getVersion()}.$ext"
            )->run();
        } catch (Throwable $ex) {
            $this->ui_util->sendFailure($this->plugin->txt("file.not_found"));
            $this->dic->ctrl()->redirectByClass(self::class, self::CMD_SHOW_VERSIONS);
        }
        exit;
    }

    /**
     * Determines the button name based on the object settings and RBAC
     */
    protected function buttonName(): string
    {
        //
        //todo if works place this to ilObjOnlyOfficeAccess

        $allow_edit = false;

        //ILIAS RBAC EDIT_FILE Access is granted
        if (ilObjOnlyOfficeAccess::hasEditFileAccess() === true) {
            $allow_edit = true;
        }

        $object_settings = $this->object_settings_repo->read($this->file_id);

        //setting ALLOW_EDIT is checked
        //setting EDITING_PERIOD is not configured
        if (
            $object_settings
            && $object_settings->isAllowEdit()
            && DateFetcher::editingPeriodIsFetchable($this->file_id) === false
        ) {
            $allow_edit = true;
        }

        //setting ALLOW_EDIT is checked
        //setting EDITING_PERIOD is configured
        //current time is within configured EDITING_PERIOD
        if (
            $object_settings
            && $object_settings->isAllowEdit()
            && DateFetcher::editingPeriodIsFetchable($this->file_id) === true
            && DateFetcher::isWithinPotentialTimeLimit($this->file_id) === true
        ) {
            $allow_edit = true;
        }

        if ($allow_edit === true) {
            return $this->plugin->txt('xono_edit_button');
        }

        return $this->plugin->txt('xono_view_button');
    }

    /**
     * generates and returns the target URL for the button
     */
    protected function buttonTarget(): string
    {
        return $this->dic->ctrl()->getLinkTargetByClass(xonoEditorGUI::class, xonoEditorGUI::CMD_EDIT);
    }

}
