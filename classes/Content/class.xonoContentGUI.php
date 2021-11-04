<?php

use srag\Plugins\OnlyOffice\StorageService\StorageService;
use srag\DIC\OnlyOffice\DIC\DICInterface;
use srag\DIC\OnlyOffice\DICStatic;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileVersionRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\ilDBFileChangeRepository;
use srag\Plugins\OnlyOffice\InfoService\InfoService;
use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;

/**
 * Class xonoContentGUI
 * @author            Theodor Truffer <theo@fluxlabs.ch>
 * @author            Sophie Pfister <sophie@fluxlabs.ch>
 * @ilCtrl_isCalledBy xonoContentGUI: ilObjOnlyOfficeGUI
 */
class xonoContentGUI extends xonoAbstractGUI
{

    use OnlyOfficeTrait;

    const BASE_URL = ILIAS_HTTP_PATH;

    /** @var ilOnlyOfficePlugin */
    protected $plugin;
    /** @var StorageService */
    protected $storage_service;
    /** @var int */
    protected $file_id;

    const CMD_STANDARD = 'showVersions';
    const CMD_SHOW_VERSIONS = 'showVersions';
    const CMD_DOWNLOAD = 'downloadFileVersion';
    const CMD_EDIT = xonoEditorGUI::CMD_EDIT;

    public function __construct(
        \ILIAS\DI\Container $dic,
        ilOnlyOfficePlugin $plugin,
        int $object_id
    ) {
        parent::__construct($dic, $plugin);
        $this->file_id = $object_id;
        $this->afterConstructor();
    }

    protected function afterConstructor()/*: void*/
    {

        $this->storage_service = new StorageService(
            self::dic()->dic(),
            new ilDBFileVersionRepository(),
            new ilDBFileRepository(),
            new ilDBFileChangeRepository()
        );
    }

    public final function getType() : string
    {
        return ilOnlyOfficePlugin::PLUGIN_ID;
    }

    public function executeCommand()
    {
        self::dic()->tabs()->activateTab(ilObjOnlyOfficeGUI::TAB_SHOW_CONTENTS);

        self::dic()->help()->setScreenIdComponent(ilOnlyOfficePlugin::PLUGIN_ID);
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
        $fileVersions = $this->storage_service->getAllVersions($this->file_id);
        $file = $this->storage_service->getFile($this->file_id);
        $ext = pathinfo($file->getTitle(), PATHINFO_EXTENSION);
        $fileName = rtrim($file->getTitle(), '.' . $ext);
        $json = json_encode($fileVersions);
        $url = $this->getDownloadUrlArray($fileVersions, $fileName, $ext);

        ilUtil::sendInfo($this->plugin->txt('xono_reload_info'));

        $tpl = $this->plugin->getTemplate('html/tpl.file_history.html');
        $tpl->setVariable('FORWARD', $this->buttonTarget());
        $tpl->setVariable('BUTTON', $this->buttonName());
        $tpl->setVariable('TBL_DATA', $json);
        $tpl->setVariable('BASE_URL', self::BASE_URL);
        $tpl->setVariable('URL', json_encode($url));
        $tpl->setVariable('FILENAME', $fileName);
        $tpl->setVariable('EXTENSION', $ext);
        $tpl->setVariable('VERSION', $this->plugin->txt('xono_version'));
        $tpl->setVariable('CREATED', $this->plugin->txt('xono_date'));
        $tpl->setVariable('EDITOR', $this->plugin->txt('xono_editor'));
        $tpl->setVariable('DOWNLOAD', $this->plugin->txt('xono_download'));
        $tpl->setVariable('LIMIT', InfoService::getNumberOfVersions());
        //$tpl->setVariable('RELOAD_INFO', );
        $content = $tpl->get();
        $this->dic->ui()->mainTemplate()->setContent($content);
    }

    /**
     * Delivers a file version for download
     */
    protected function downloadFileVersion()
    {
        $path = $_GET['path'];
        $name = $_GET['name'];
        $mime_type = $_GET['mime'];
        ilFileDelivery::deliverFileAttached($path, $name, $mime_type);
        exit;
    }

    /**
     * Determines the button name based on the object settings and RBAC
     * @return string
     */
    protected function buttonName()
    {
        if (self::onlyOffice()->objectSettings()->getObjectSettingsById($this->file_id)->allowEdit() ||
            ilObjOnlyOfficeAccess::hasEditFileAccess()) {
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

    /**
     * generates and returns the URL that is used to download the file
     */
    protected function getDownloadUrlArray(array $fileVersions, string $filename, string $extension) : array
    {
        $file = $this->storage_service->getFile($this->file_id);

        $result = array();
        foreach ($fileVersions as $fv) {
            $url = ILIAS_ABSOLUTE_PATH . '/data/' . CLIENT_ID . $fv->getUrl();
            $version = $fv->getVersion();
            $name = $filename . '_V' . $version . '.' . $extension;
            $this->dic->ctrl()->setParameter($this, 'path', $url);
            $this->dic->ctrl()->setParameter($this, 'name', $name);
            $this->dic->ctrl()->setParameter($this, 'mime', $file->getMimeType());
            $path = $this->dic->ctrl()->getLinkTarget($this, self::CMD_DOWNLOAD);
            $result[$version] = '/' . $path;
        }
        return $result;
    }

    /**
     * Get DIC interface
     * @return DICInterface DIC interface
     */
    protected static final function dic() : DICInterface
    {
        return DICStatic::dic();
    }

}