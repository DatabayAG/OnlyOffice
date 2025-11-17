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
use ILIAS\Plugin\OnlyOffice\Form\PluginConfigForm;
use ILIAS\Plugin\OnlyOffice\Repository;
use ILIAS\Plugin\OnlyOffice\ObjectSettings\ObjectSettings;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\File;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\FileVersion;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\Common\UUID;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use ILIAS\Plugin\OnlyOffice\CryptoService\JwtService;
use ILIAS\Plugin\OnlyOffice\CryptoService\WebAccessService;
use ILIAS\Plugin\OnlyOffice\Utils\DateFetcher;

class xonoEditorGUI extends xonoAbstractGUI
{
    protected ilOnlyOfficePlugin $plugin;
    protected StorageService $storage_service;
    protected int $file_id;
    public const CMD_EDIT = "editFile";
    public const CMD_STANDARD = "editFile";
    public const BASE_URL = ILIAS_HTTP_PATH;
    protected string $onlyoffice_url;
    protected string $onlyoffice_key;
    private Repository $repo;

    public function __construct(
        Container $dic,
        ilOnlyOfficePlugin $plugin,
        int $object_id
    ) {
        parent::__construct($dic, $plugin);

        $this->onlyoffice_url = $this->plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_URL, "");
        $this->onlyoffice_key = $this->plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_SECRET, "");

        $this->file_id = $object_id;
        $this->repo = Repository::getInstance();

        $this->afterConstructor();
    }

    protected function afterConstructor(): void
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

    /**
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        $this->dic->help()->setScreenIdComponent(ilOnlyOfficePlugin::PLUGIN_ID);
        $next_class = $this->dic->ctrl()->getNextClass($this);
        $cmd = $this->dic->ctrl()->getCmd(self::CMD_STANDARD);

        switch ($next_class) {
            default:
                switch ($cmd) {
                    default:
                        $this->{$cmd}();
                        break;
                }

        }
    }

    protected function editFile(): void
    {
        $object_settings = $this->repo->objectSettings()->getObjectSettingsById($this->file_id);

        $file = $this->storage_service->getFile($this->file_id);
        $latest_version = null;
        $all_versions = null;

        if (!is_null($file)) {
            $all_versions = $this->storage_service->getAllVersions($this->file_id);
            $latest_version = $this->storage_service->getLatestVersion($file->getUuid());
        }

        $tpl = $this->plugin->getTemplate('html/tpl.editor.html');

        $withinPotentialTimelimit = true;

        if (!is_null($object_settings)) {
            if (ilObjOnlyOfficeAccess::hasEditFileAccess() === false) {
                $withinPotentialTimelimit = DateFetcher::isWithinPotentialTimeLimit($file->getObjId());
                $tpl->setVariable('IS_LIMITED', $object_settings->isLimitedPeriod());
                $tpl->setVariable('WITHIN_POTENTIAL_TIME_LIMIT', $withinPotentialTimelimit);
                if (DateFetcher::editingPeriodIsFetchable($this->file_id)) {
                    $editing_period = DateFetcher::fetchEditingPeriod($this->file_id);
                    $tpl->setVariable('EDIT_PERIOD_TXT', sprintf($this->plugin->txt('editor_edit_period'), $editing_period));
                    $tpl->setVariable('TIME_UP_TXT', $this->plugin->txt('editor_edit_timeup'));
                    $tpl->setVariable('TIME_WAS_UP_TXT', $this->plugin->txt('editor_edit_timewasup'));
                    $tpl->setVariable('START_TIME', $object_settings->getStartTime());
                    $tpl->setVariable('END_TIME', $object_settings->getEndTime());
                }
            }
        }

        $tpl->setVariable('BUTTON', $this->plugin->txt('xono_back_button'));
        $tpl->setVariable('SCRIPT_SRC', $this->onlyoffice_url . '/web-apps/apps/api/documents/api.js');
        $tpl->setVariable('RETURN', $this->generateReturnUrl());

        if (!is_null($file) && !is_null($latest_version) && !is_null($all_versions)) {
            $tpl->setVariable('FILE_TITLE', $file->getTitle());
            $tpl->setVariable('CONFIG', $this->config($file, $latest_version, $object_settings, $withinPotentialTimelimit));
            $tpl->setVariable('LATEST', $latest_version->getVersion());
            $tpl->setVariable('HISTORY_DATA', $this->historyData($all_versions));
            $tpl->setVariable('HISTORY', $this->history($latest_version, $all_versions));
        }

        $content = $tpl->get();
        echo $content;
        exit;

    }

    /**
     * Builds and returns the config array as string
     */
    protected function config(File $file, FileVersion $fileVersion, ObjectSettings $objectSettings, bool $withinPotentialTimeLimit): string
    {
        $as_array = []; // Config Array
        $extension = pathinfo($fileVersion->getUrl(), PATHINFO_EXTENSION);

        // general config
        $as_array['documentType'] = File::determineDocType($extension)->getEditorType();

        // document config
        $document = []; // SubArray "document"
        $document['fileType'] = $file->getFileType();
        $document['key'] = $this->generateDocumentKey($fileVersion);
        $document['title'] = $file->getTitle();
        $document['url'] = self::BASE_URL . ltrim(WebAccessService::getWACUrl($fileVersion->getUrl()), ".");
        $as_array['document'] = $document;

        // editor config
        $editor = []; // SubArray "editor"
        $editor['callbackUrl'] = $this->generateCallbackUrl(
            $file->getUuid(),
            $file->getObjId(),
            $extension
        );
        $editor['user'] = $this->buildUserArray($this->dic->user()->getId());
        $editor['mode'] = $this->determineAccessRights($withinPotentialTimeLimit);
        $editor['lang'] = $this->dic->user()->getLanguage();
        $editor['customization'] = [
            "plugins" => false,
            "forcesave" => true];
        $as_array['editorConfig'] = $editor;

        // events config
        $as_array['events'] = ["onRequestHistory" => "#!!onRequestHistory!!#",
                                    "onRequestHistoryData" => "#!!onRequestHistoryData!!#",
                                    "onDocumentStateChange" => "#!!onDocumentStateChange!!#",
                                    "onAppReady" => "#!!onAppReady!!#"
        ];

        // add token
        $token = JwtService::jwtEncode($as_array, $this->onlyoffice_key);
        $as_array['token'] = $token;

        // convert to valid string
        $result = json_encode($as_array);
        $result = str_replace('"#!!', '', $result);
        $result = str_replace('!!#"', '', $result);
        return $result;

    }

    /**
     * Builds and returns an array containing the version history of a file as string
     */
    protected function history(FileVersion $latestVersion, array $all_versions): string
    {
        $all_changes = $this->storage_service->getAllChanges($latestVersion->getFileUuid()->asString());
        $history_array = [];

        // add all versions to history
        foreach ($all_versions as $version) {
            $v = $version->getVersion();
            $info_array = [
                "changes" => '#!!JSON.parse("' . $all_changes[$v]->getChangesObjectString() . '")!!#',
                "created" => rtrim($version->getCreatedAt()->__toString(), '<br>'),
                "key" => $this->generateDocumentKey($version),
                "serverVersion" => $all_changes[$v]->getServerVersion(),
                "user" => $this->buildUserArray($version->getUserId()),
                "version" => $version->getVersion()
            ];
            $history_array[] = $info_array;
        }

        // convert to valid string
        $result = json_encode($history_array);
        $result = str_replace('(\"[{', '("[{', $result);
        $result = str_replace('}]\")', '}]")', $result);
        $result = str_replace('(\"{', '("{', $result);
        $result = str_replace('}\")', '}")', $result);
        $result = str_replace('"#!!', '', $result);
        $result = str_replace('!!#"', '', $result);

        return $result;
    }

    /**
     * Builds and returns an array containing information about all file versions (as string)
     */
    protected function historyData(array $allVersions): string
    {
        $result = [];
        foreach ($allVersions as $version) {
            $data_array = [];
            $v = $version->getVersion();
            $uuid = $version->getFileUuid()->asString();

            $change_url = $this->storage_service->getChangeUrl($uuid, $v);
            $data_array['changesUrl'] = self::BASE_URL . ltrim(WebAccessService::getWACUrl($change_url), '.');

            $data_array['key'] = $uuid . '-' . $v;

            if ($v > 1) {
                $data_array['previous'] = $this->buildPreviousArray($version);
            }

            $data_array['url'] = self::BASE_URL . ltrim(WebAccessService::getWACUrl($version->getUrl()), '.');

            $data_array['version'] = $v;

            //token
            $token = JwtService::jwtEncode($data_array, $this->onlyoffice_key);
            $data_array['token'] = $token;
            $result[$v] = $data_array;

        }
        return json_encode($result);
    }

    /* --- Helper Methods --- */
    /**
     * Generates the URL for the return button
     * @throws ilCtrlException
     */
    protected function generateReturnUrl(): string
    {
        $content_gui = new xonoContentGUI($this->dic, $this->plugin, $this->file_id);
        return $this->dic->ctrl()->getLinkTarget($content_gui, xonoContentGUI::CMD_SHOW_VERSIONS);

    }

    /**
     * generates the callback URL for the only office document server
     */
    protected function generateCallbackUrl(UUID $file_uuid, int $file_id, string $extension): string
    {
        $path = 'Customizing/global/plugins/Services/Repository/RepositoryObject/OnlyOffice/save.php?' .
            '&uuid=' . $file_uuid->asString() .
            '&file_id=' . $file_id .
            '&client_id=' . CLIENT_ID .
            '&ext=' . $extension;
        return self::BASE_URL . '/' . $path;
    }

    protected function generateDocumentKey(FileVersion $fv): string
    {
        return $fv->getFileUuid()->asString() . '-' . $fv->getVersion();
    }

    protected function buildPreviousArray(FileVersion $version): array
    {
        $result = [];
        $previous = $this->storage_service->getPreviousVersion(
            $version->getFileUuid()->asString(),
            $version->getVersion()
        );
        $key = $previous->getFileUuid()->asString() . '-' . $previous->getVersion();
        $result['key'] = $key;
        $url = self::BASE_URL . ltrim(WebAccessService::getWACUrl($previous->getUrl()), '.');
        $result['url'] = $url;
        return $result;
    }

    protected function buildUserArray(int $user_id): array
    {
        if (!ilObjUser::userExists([$user_id])) {
            return ["id" => $user_id, "name" => "(deleted)"];
        }
        $user = new ilObjUser($user_id);
        return ["id" => $user_id, "name" => $user->getPublicName()];
    }

    /**
     * Determines access rights based on object settings and RBAC
     */
    protected function determineAccessRights(bool $withinPotentialTimeLimit): string
    {
        if (
            (
                $this->repo->objectSettings()->getObjectSettingsById($this->file_id)->allowEdit()
                &&
                $withinPotentialTimeLimit
            )
            || ilObjOnlyOfficeAccess::hasEditFileAccess()
        ) {
            return "edit";
        } else {
            return "view";
        }
    }
}
