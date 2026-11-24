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
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Enum\PluginAsset;
use ILIAS\Plugin\OnlyOffice\Form\PluginConfigForm;
use ILIAS\Plugin\OnlyOffice\Model\File;
use ILIAS\Plugin\OnlyOffice\Model\FileVersion;
use ILIAS\Plugin\OnlyOffice\Model\ObjectSettings;
use ILIAS\Plugin\OnlyOffice\Repository\ObjectSettingsRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\StorageService;
use ILIAS\Plugin\OnlyOffice\Utils\DateFetcher;

class xonoEditorGUI extends xonoAbstractGUI
{
    public const string CMD_EDIT = "editFile";
    public const string CMD_STANDARD = "editFile";
    public const string BASE_URL = ILIAS_HTTP_PATH;

    protected ilOnlyOfficePlugin $plugin;
    protected string $onlyoffice_url;
    protected string $onlyoffice_key;
    private readonly ilGlobalTemplateInterface $main_tpl;
    private readonly ObjectSettingsRepository $object_settings_repo;
    private readonly StorageService $storage_service;

    public function __construct(
        Container $dic,
        ilOnlyOfficePlugin $plugin,
        protected int $file_id
    ) {
        parent::__construct($dic, $plugin);

        $this->onlyoffice_url = $this->plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_URL, "");
        $this->onlyoffice_key = $this->plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_SECRET, "");
        $this->main_tpl = $this->dic->ui()->mainTemplate();
        $this->storage_service = $this->plugin->getStorageService();

        $this->object_settings_repo = new ObjectSettingsRepository($this->dic->database());
    }

    final public function getType(): string
    {
        return ilOnlyOfficePlugin::ID;
    }

    /**
     * @throws ilCtrlException
     */
    public function executeCommand(): void
    {
        $this->dic->help()->setScreenIdComponent(ilOnlyOfficePlugin::ID);
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
        $object_settings = $this->object_settings_repo->read($this->file_id);

        $file = $this->storage_service->getFile($this->file_id);
        $latest_version = null;
        $all_versions = null;

        if (!is_null($file)) {
            $all_versions = $this->storage_service->getAllVersions($this->file_id);
            $latest_version = $this->storage_service->getLatestVersion($file->getUuid());
        }

        $this->main_tpl->addJavaScript($this->plugin->assetsFile(PluginAsset::Js, "editor.js"));
        $this->main_tpl->addCss($this->plugin->assetsFile(PluginAsset::Css, "editor.css"));

        $tpl = new ilTemplate($this->plugin->assetsFile(PluginAsset::Templates, "tpl.editor.html", false), true, true);

        $tpl->setVariable("BACK_BUTTON", $this->plugin->txt("xono_back_button"));
        $tpl->setVariable("API_SCRIPT_SRC", $this->onlyoffice_url . "/web-apps/apps/api/documents/api.js");

        $within_potential_time_limit = true;

        $editing_period = null;
        if (!is_null($object_settings)) {
            $within_potential_time_limit = DateFetcher::isWithinPotentialTimeLimit($file->getObjId());
            if (DateFetcher::editingPeriodIsFetchable($this->file_id)) {
                $editing_period = DateFetcher::fetchEditingPeriod($this->file_id);
            }
        }

        $current_version = null;
        $history_data = [];
        $history = [];
        $only_office_config = [];
        if (!is_null($file) && !is_null($latest_version) && !is_null($all_versions)) {
            $tpl->setVariable('FILE_TITLE', $file->getTitle());
            $only_office_config = $this->config($file, $latest_version, $object_settings, $within_potential_time_limit);
            $current_version = $latest_version->getVersion();
            $history_data = $this->historyData($all_versions);
            $history = $this->history($latest_version, $all_versions);
        }

        $this->main_tpl->addOnLoadCode(
            "window." . "config_" . ilOnlyOfficePlugin::ID . " = "
            . json_encode([
                "editing" => [
                    "limited" => $object_settings->isLimitedPeriod(),
                    "withinTimeLimit" => $within_potential_time_limit,
                    "startTime" => $object_settings->getStartTime()?->getUnixTime(),
                    "endTime" => $object_settings->getEndTime()->getUnixTime(),
                ],
                "file" => [
                    "currentVersion" => $current_version,
                    "historyData" => $history_data,
                    "history" => $history
                ],
                "onlyOfficeConfig" => $only_office_config,
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
        $this->main_tpl->setContent($content);
    }

    /**
     * Builds and returns the config array
     */
    protected function config(File $file, FileVersion $file_version, ObjectSettings $object_settings, bool $within_potential_time_limit): array
    {
        $as_array = []; // Config Array

        // general config
        $as_array['documentType'] = FileCreationType::determineDocType($file->getFileType())->getEditorType();

        // document config
        $document = []; // SubArray "document"
        $document['fileType'] = $file->getFileType();
        $document['key'] = $this->generateDocumentKey($file_version);
        $document['title'] = $file->getTitle();
        $document['url'] = $file_version->getRid() ? $this->storage_service->getSource($file_version->getRid()) : "";
        $as_array['document'] = $document;

        // editor config
        $editor = []; // SubArray "editor"
        $editor['callbackUrl'] = $this->generateCallbackUrl(
            $file->getUuid(),
            $file->getObjId(),
            $file->getFileType()
        );
        $editor['user'] = $this->buildUserArray($this->dic->user()->getId());
        $editor['mode'] = $this->determineAccessRights($within_potential_time_limit);
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
     * @param list<FileVersion> $all_versions
     */
    protected function history(FileVersion $latest_version, array $all_versions): array
    {
        $all_changes = $this->storage_service->getAllChanges($latest_version->getFileUuid());
        $history_array = [];

        // add all versions to history
        foreach ($all_versions as $file_version) {
            $version = $file_version->getVersion();

            if (!isset($all_changes[$version])) {
                continue;
            }

            $info_array = [
                "changes" => json_decode($all_changes[$version]->getChangesObjectString(), true, 512, JSON_THROW_ON_ERROR),
                "created" => rtrim($file_version->getCreatedAt()->__toString(), '<br>'),
                "key" => $this->generateDocumentKey($file_version),
                "serverVersion" => $all_changes[$version]->getServerVersion(),
                "user" => $this->buildUserArray($file_version->getUserId()),
                "version" => $file_version->getVersion()
            ];
            $history_array[] = $info_array;
        }

        return $history_array;
    }

    /**
     * Builds and returns an array containing information about all file versions
     * @param list<FileVersion> $all_versions
     */
    protected function historyData(array $all_versions): array
    {
        $result = [];
        foreach ($all_versions as $version) {
            $data_array = [];
            $v = $version->getVersion();
            $uuid = $version->getFileUuid()->toString();

            $data_array['changesUrl'] = $this->storage_service->getChangeUrl($version);

            $data_array['key'] = $uuid . '-' . $v;

            if ($v > 1) {
                $data_array['previous'] = $this->buildPreviousArray($version);
            }

            $data_array['url'] = $version->getRid() ? $this->storage_service->getSource($version->getRid()) : "";

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
            $version->getFileUuid(),
            $version->getVersion()
        );
        $key = $previous->getFileUuid()->toString() . '-' . $previous->getVersion();
        $result['key'] = $key;
        $result['url'] = $previous->getRid() ? $this->storage_service->getSource($previous->getRid()) : "";
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
    protected function determineAccessRights(bool $within_potential_time_limit): string
    {

        $has_edit_access = ilObjOnlyOfficeAccess::hasEditFileAccess();

        if (!$has_edit_access) {
            $object_settings = $this->object_settings_repo->read($this->file_id);
            if (
                $object_settings
                && $object_settings->isAllowEdit()
                && $within_potential_time_limit
            ) {
                $has_edit_access = true;
            }
        }

        if ($has_edit_access) {
            return "edit";
        }

        return "view";
    }
}
