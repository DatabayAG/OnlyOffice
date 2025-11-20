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

use ILIAS\Data\UUID\Uuid;
use ILIAS\DI\Container;
use ILIAS\Plugin\OnlyOffice\CryptoService\JwtService;
use ILIAS\Plugin\OnlyOffice\CryptoService\WebAccessService;
use ILIAS\Plugin\OnlyOffice\Enum\PluginAsset;
use ILIAS\Plugin\OnlyOffice\Form\PluginConfigForm;
use ILIAS\Plugin\OnlyOffice\ObjectSettings\ObjectSettings;
use ILIAS\Plugin\OnlyOffice\Repository;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\File;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\FileVersion;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
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
    private ilGlobalTemplateInterface $mainTpl;

    public function __construct(
        Container          $dic,
        ilOnlyOfficePlugin $plugin,
        int                $object_id
    )
    {
        parent::__construct($dic, $plugin);

        $this->onlyoffice_url = $this->plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_URL, "");
        $this->onlyoffice_key = $this->plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_SECRET, "");

        $this->file_id = $object_id;
        $this->repo = Repository::getInstance();
        $this->mainTpl = $this->dic->ui()->mainTemplate();

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

        switch ($cmd) {
            default:
                $this->{$cmd}();
                break;
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

        $this->mainTpl->addJavaScript($this->plugin->assetsFile(PluginAsset::Js, "editor.js"));
        $this->mainTpl->addCss($this->plugin->assetsFile(PluginAsset::Css, "editor.css"));

        $tpl = new ilTemplate($this->plugin->assetsFile(PluginAsset::Templates, "tpl.editor.html", false), true, true);

        $tpl->setVariable("BACK_BUTTON", $this->plugin->txt("xono_back_button"));
        $tpl->setVariable("API_SCRIPT_SRC", $this->onlyoffice_url . "/web-apps/apps/api/documents/api.js");

        $withinPotentialTimeLimit = true;

        $editing_period = null;
        if (!is_null($object_settings) && ilObjOnlyOfficeAccess::hasEditFileAccess() === false) {
            $withinPotentialTimeLimit = DateFetcher::isWithinPotentialTimeLimit($file->getObjId());
            if (DateFetcher::editingPeriodIsFetchable($this->file_id)) {
                $editing_period = DateFetcher::fetchEditingPeriod($this->file_id);
            }
        }

        $currentVersion = null;
        $historyData = [];
        $history = [];
        $onlyOfficeConfig = [];
        if (!is_null($file) && !is_null($latest_version) && !is_null($all_versions)) {
            $tpl->setVariable('FILE_TITLE', $file->getTitle());
            $onlyOfficeConfig = $this->config($file, $latest_version, $object_settings, $withinPotentialTimeLimit);
            $currentVersion = $latest_version->getVersion();
            $historyData = $this->historyData($all_versions);
            $history = $this->history($latest_version, $all_versions);
        }

        $this->mainTpl->addOnLoadCode(
            "window." . "config_" . ilOnlyOfficePlugin::PLUGIN_ID . " = "
            . json_encode([
                "editing" => [
                    "limited" => $object_settings->isLimitedPeriod(),
                    "withinTimeLimit" => $withinPotentialTimeLimit,
                    "startTime" => $object_settings->getStartTime(),
                    "endTime" => $object_settings->getEndTime(),
                ],
                "file" => [
                    "currentVersion" => $currentVersion,
                    "historyData" => $historyData,
                    "history" => $history
                ],
                "onlyOfficeConfig" => $onlyOfficeConfig,
                "backTarget" => $this->generateReturnUrl(),
            ], JSON_THROW_ON_ERROR)
        );

        $this->dic->language()->toJSMap([
            "editor_edit_period" => $editing_period
                ? sprintf($this->plugin->txt('editor_edit_period'), $editing_period)
                : "",
            "editor_edit_timeup" => $this->plugin->txt('editor_edit_timeup'),
            "editor_edit_timewasup" => $this->plugin->txt('editor_edit_timewasup'),
        ]);


        $content = $tpl->get();
        $this->mainTpl->setContent($content);
    }

    /**
     * Builds and returns the config array
     */
    protected function config(File $file, FileVersion $fileVersion, ObjectSettings $objectSettings, bool $withinPotentialTimeLimit): array
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

        // events config // function added/set in js code
        $as_array['events'] = [
            "onRequestHistory" => "",
            "onRequestHistoryData" => "",
            "onDocumentStateChange" => "",
            "onAppReady" => ""
        ];

        // add token
        $token = JwtService::jwtEncode($as_array, $this->onlyoffice_key);
        $as_array['token'] = $token;

        return $as_array;
    }

    /**
     * Builds and returns an array containing the version history of a file
     */
    protected function history(FileVersion $latestVersion, array $all_versions): array
    {
        $all_changes = $this->storage_service->getAllChanges($latestVersion->getFileUuid()->toString());
        $history_array = [];

        // add all versions to history
        foreach ($all_versions as $version) {
            $v = $version->getVersion();
            $info_array = [
                "changes" => json_decode($all_changes[$v]->getChangesObjectString(), true),
                "created" => rtrim($version->getCreatedAt()->__toString(), '<br>'),
                "key" => $this->generateDocumentKey($version),
                "serverVersion" => $all_changes[$v]->getServerVersion(),
                "user" => $this->buildUserArray($version->getUserId()),
                "version" => $version->getVersion()
            ];
            $history_array[] = $info_array;
        }

        return $history_array;
    }

    /**
     * Builds and returns an array containing information about all file versions
     */
    protected function historyData(array $allVersions): array
    {
        $result = [];
        foreach ($allVersions as $version) {
            $data_array = [];
            $v = $version->getVersion();
            $uuid = $version->getFileUuid()->toString();

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
        return $result;
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
    protected function generateCallbackUrl(Uuid $file_uuid, int $file_id, string $extension): string
    {
        $path = 'Customizing/global/plugins/Services/Repository/RepositoryObject/OnlyOffice/save.php?' .
            '&uuid=' . $file_uuid->toString() .
            '&file_id=' . $file_id .
            '&client_id=' . CLIENT_ID .
            '&ext=' . $extension;
        return self::BASE_URL . '/' . $path;
    }

    protected function generateDocumentKey(FileVersion $fv): string
    {
        return $fv->getFileUuid()->toString() . '-' . $fv->getVersion();
    }

    protected function buildPreviousArray(FileVersion $version): array
    {
        $result = [];
        $previous = $this->storage_service->getPreviousVersion(
            $version->getFileUuid()->toString(),
            $version->getVersion()
        );
        $key = $previous->getFileUuid()->toString() . '-' . $previous->getVersion();
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
        }

        return "view";
    }
}
