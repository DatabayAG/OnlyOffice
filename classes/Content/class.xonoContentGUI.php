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
use ILIAS\Plugin\OnlyOffice\Form\PluginConfigForm;
use ILIAS\Plugin\OnlyOffice\Repository;
use ILIAS\Refinery\Factory;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\FileVersion;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\Plugin\OnlyOffice\Utils\DateFetcher;

class xonoContentGUI extends xonoAbstractGUI
{
    public const BASE_URL = ILIAS_HTTP_PATH;

    protected ilOnlyOfficePlugin $plugin;
    protected StorageService $storage_service;
    protected int $file_id;
    private $tpl;

    public const CMD_STANDARD = 'showVersions';
    public const CMD_SHOW_VERSIONS = 'showVersions';
    public const CMD_DOWNLOAD = 'downloadFileVersion';
    public const CMD_EDIT = xonoEditorGUI::CMD_EDIT;
    private Factory $refinery;
    private WrapperFactory $httpWrapper;
    private Repository $repo;

    public function __construct(
        Container $dic,
        ilOnlyOfficePlugin $plugin,
        int $object_id
    ) {
        global $DIC;

        $this->refinery = $DIC->refinery();
        $this->httpWrapper = $DIC->http()->wrapper();

        parent::__construct($dic, $plugin);
        $this->file_id = $object_id;
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->repo = Repository::getInstance();

        $this->afterConstructor();
    }

    protected function afterConstructor()/*: void*/
    {

        $this->storage_service = new StorageService(
            $this->dic,
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );
    }

    final public function getType(): string
    {
        return ilOnlyOfficePlugin::PLUGIN_ID;
    }

    public function executeCommand()
    {
        $this->dic->tabs()->activateTab(ilObjOnlyOfficeGUI::TAB_SHOW_CONTENTS);

        $this->dic->help()->setScreenIdComponent(ilOnlyOfficePlugin::PLUGIN_ID);
        $next_class = $this->dic->ctrl()->getNextClass($this);
        $cmd = $this->dic->ctrl()->getCmd(self::CMD_STANDARD);

        switch (strtolower($next_class)) {
            case strtolower(xonoEditorGUI::class):
                $xono_editor = new xonoEditorGUI($this->dic, $this->plugin, $this->file_id);
                $this->dic->ctrl()->forwardCommand($xono_editor);
                break;
            default:
                switch ($cmd) {
                    case self::CMD_EDIT:
                        $this->dic->ctrl()->redirectByClass(xonoEditorGUI::class, xonoEditorGUI::CMD_EDIT);
                        break;
                    default:
                        $this->{$cmd}();
                        break;
                }
        }
    }

    /**
     * Fetches the information about all versions of a file from the database
     * Renders the GUI for content
     */
    protected function showVersions()
    {
        /** @var FileVersion[] $fileVersions */
        $fileVersions = $this->storage_service->getAllVersions($this->file_id);
        $file = $this->storage_service->getFile($this->file_id);
        if (is_null($file)) {
            $this->dic->ui()->mainTemplate()->setContent("");
            return;
        }

        $this->tpl->setOnScreenMessage('info', $this->plugin->txt("xono_reload_info"), true);

        $tpl = $this->plugin->getTemplate('html/tpl.file_history.html');
        $tpl->setVariable('VERSION', $this->plugin->txt('xono_version'));
        $tpl->setVariable('CREATED', $this->plugin->txt('xono_date'));
        $tpl->setVariable('EDITOR', $this->plugin->txt('xono_editor'));
        $tpl->setVariable('DOWNLOAD', $this->plugin->txt('xono_download'));
        $tpl->setVariable('FORWARD', $this->buttonTarget());
        $tpl->setVariable('BUTTON', $this->buttonName());

        $limit = (int) $this->plugin->settings->get(PluginConfigForm::KEY_NUM_VERSIONS, "10");
        $fileVersionsAdded = 0;
        foreach ($fileVersions as $fileVersion) {
            if ($fileVersionsAdded >= $limit) {
                break;
            }
            $user = new ilObjUser($fileVersion->getUserId());
            $tpl->setVariable('TABLE_ROW_VERSION', $fileVersion->getVersion());
            $tpl->setVariable(
                'TABLE_ROW_CREATED_AT',
                $fileVersion->getCreatedAt()->get(
                    IL_CAL_FKT_DATE,
                    'd.m.Y H:i',
                    $this->dic->user()->getTimeZone()
                )
            );
            $tpl->setVariable('TABLE_ROW_USER', $user->getPublicName());
            $this->dic->ctrl()->setParameter($this, "version", $fileVersion->getVersion());
            $tpl->setVariable('TABLE_ROW_DOWNLOAD_URL', $this->dic->ctrl()->getLinkTarget($this, self::CMD_DOWNLOAD));
            $fileVersionsAdded++;
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
    protected function downloadFileVersion()
    {
        $requestedVersion = $this->httpWrapper->query()->retrieve(
            "version",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if ($requestedVersion === null) {
            $this->dic->ctrl()->redirectByClass(xonoContentGUI::class, xonoContentGUI::CMD_SHOW_VERSIONS);
        }

        /** @var FileVersion $version */
        $fileVersion = null;
        foreach ($this->storage_service->getAllVersions($this->file_id) as $version) {
            if ($version->getVersion() === $requestedVersion) {
                $fileVersion = $version;
                break;
            }
        }

        $file = $this->storage_service->getFile($this->file_id);

        if (!$fileVersion || !$file) {
            $this->dic->ctrl()->redirectByClass(xonoContentGUI::class, xonoContentGUI::CMD_SHOW_VERSIONS);

        }

        $path = ILIAS_ABSOLUTE_PATH . '/data/' . CLIENT_ID . $fileVersion->getUrl();
        $ext = pathinfo($file->getTitle(), PATHINFO_EXTENSION);
        $fileName = rtrim($file->getTitle(), '.' . $ext);
        ilFileDelivery::deliverFileAttached(
            $path,
            "{$fileName}_V{$fileVersion->getVersion()}.$ext",
            $file->getMimeType()
        );
        exit;
    }

    /**
     * Determines the button name based on the object settings and RBAC
     * @return string
     */
    protected function buttonName()
    {
        //
        //todo if works place this to ilObjOnlyOfficeAccess

        $allowEdit = false;

        //ILIAS RBAC EDIT_FILE Access is granted
        if (ilObjOnlyOfficeAccess::hasEditFileAccess() === true) {
            $allowEdit = true;
        }

        //setting ALLOW_EDIT is checked
        //setting EDITING_PERIOD is not configured
        if (
            $this->repo->objectSettings()->getObjectSettingsById($this->file_id)->allowEdit() === true
            &&
            DateFetcher::editingPeriodIsFetchable($this->file_id) === false
        ) {
            $allowEdit = true;
        }

        //setting ALLOW_EDIT is checked
        //setting EDITING_PERIOD is configured
        //current time is within configured EDITING_PERIOD
        if (
            $this->repo->objectSettings()->getObjectSettingsById($this->file_id)->allowEdit() === true
            &&
            DateFetcher::editingPeriodIsFetchable($this->file_id) === true
            &&
            DateFetcher::isWithinPotentialTimeLimit($this->file_id) === true
        ) {
            $allowEdit = true;
        }

        ////

        if ($allowEdit === true) {
            return $this->plugin->txt('xono_edit_button');
        } else {
            return $this->plugin->txt('xono_view_button');
        }
    }

    /**
     * generates and returns the target URL for the button
     */
    protected function buttonTarget()
    {
        return $this->dic->ctrl()->getLinkTargetByClass(xonoEditorGUI::class, xonoEditorGUI::CMD_EDIT);
    }

}
