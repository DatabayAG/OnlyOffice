<?php

namespace srag\Plugins\OnlyOffice\StorageService;

use ilDateTime;
use ilDateTimeException;
use ILIAS\DI\Container;
use ILIAS\Filesystem\Exception\IOException;
use ILIAS\FileUpload\DTO\UploadResult;
use ilObjOnlyOfficeGUI;
use srag\Plugins\OnlyOffice\StorageService\DTO\File;
use srag\Plugins\OnlyOffice\StorageService\DTO\FileTemplate;
use srag\Plugins\OnlyOffice\StorageService\DTO\FileVersion;
use srag\Plugins\OnlyOffice\StorageService\FileSystem\FileSystemService;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\Common\UUID;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileVersionRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileChangeRepository;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileAR;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileVersionAR;
use srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileChangeAR;
use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;

/**
 * Class StorageService
 * @package srag\Plugins\OnlyOffice\StorageService
 * @author  Theodor Truffer <theo@fluxlabs.ch>
 *          Sophie Pfister <sophie@fluxlabs.ch>
 */
class StorageService
{
    use OnlyOfficeTrait;

    protected Container $dic;
    protected FileVersionRepository $file_version_repository;
    protected FileSystemService $file_system_service;
    protected FileRepository $file_repository;
    protected FileChangeRepository $file_change_repository;

    public function __construct(
        Container $dic,
        FileVersionRepository $file_version_repository,
        FileRepository $file_repository,
        FileChangeRepository $file_change_repository
    ) {
        $this->dic = $dic;
        $this->file_version_repository = $file_version_repository;
        $this->file_repository = $file_repository;
        $this->file_system_service = new FileSystemService($dic);
        $this->file_change_repository = $file_change_repository;
    }

    /**
     * @throws IOException
     */
    public function createNewFileFromUpload(UploadResult $upload_result, int $obj_id): File
    {
        // Create DB Entries for File & FileVersion
        $new_file_id = new UUID();
        $path = $this->file_system_service->storeUploadResult($upload_result, $obj_id, $new_file_id->asString());
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $this->file_repository->create($new_file_id, $obj_id, $upload_result->getName(), $extension, $upload_result->getMimeType());
        list($created_at, $version) = $this->createNewFile($new_file_id, $path);

        // Create & Return FileVersion object
        $file_version = new FileVersion($version, $created_at, $this->dic->user()->getId(), $path, $new_file_id);
        $file = new File($new_file_id, $obj_id, $upload_result->getName(), $extension, $upload_result->getMimeType());
        return $file;
    }

    public function createNewFileFromDraft(string $title, int $obj_id): File
    {
        $new_file_id = new UUID();
        $path = $this->createFileDraft(
            $title,
            ilObjOnlyOfficeGUI::FILE_EXTENSIONS[$_POST[ilObjOnlyOfficeGUI::POST_VAR_FILE_CREATION_SETTING]],
            $obj_id,
            $new_file_id->asString()
        );

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // Create DB Entries for File & FileVersion
        $this->file_repository->create($new_file_id, $obj_id, basename($path), $extension, $this->dic->filesystem()->web()->getMimeType($path));
        list($created_at, $version) = $this->createNewFile($new_file_id, $path);

        // Create & Return FileVersion object
        $file_version = new FileVersion($version, $created_at, $this->dic->user()->getId(), $path, $new_file_id);
        $file = new File($new_file_id, $obj_id, basename($path), $extension, $this->dic->filesystem()->web()->getMimeType($path));
        return $file;
    }

    public function createNewFileFromTemplate(string $title, string $template_path, int $obj_id): File
    {
        $new_file_id = new UUID();
        $extension = pathinfo($template_path, PATHINFO_EXTENSION);

        $path = $this->createFileFromTemplate(
            $title,
            $template_path,
            $obj_id,
            $new_file_id->asString()
        );

        // Create DB Entries for File & FileVersion
        $this->file_repository->create($new_file_id, $obj_id, basename($path), $extension, $this->dic->filesystem()->web()->getMimeType($path));
        list($created_at, $version) = $this->createNewFile($new_file_id, $path);

        // Create & Return FileVersion object
        $file_version = new FileVersion($version, $created_at, $this->dic->user()->getId(), $path, $new_file_id);
        $file = new File($new_file_id, $obj_id, basename($path), $extension, $this->dic->filesystem()->web()->getMimeType($path));
        return $file;
    }

    /**
     * @throws ilDateTimeException
     */
    public function updateFileFromUpload(
        string $file_content,
        int $file_id,
        UUID $uuid,
        int $editor_id,
        string $file_extension,
        string $changes_object,
        string $serverVersion,
        string $change_content,
        string $change_extension
    ): FileVersion {

        // Store FileVersion and Create Database Entry
        $created_at = new ilDateTime(time(), IL_CAL_UNIX);
        $version = $this->getLatestVersion($uuid)->getVersion() + 1;
        echo $version;
        $path = $this->file_system_service->storeNewVersionFromString(
            $file_content,
            $file_id,
            $uuid->asString(),
            $version,
            $file_extension
        );
        $this->file_version_repository->create($uuid, $editor_id, $created_at, $path);

        //Store Changes and Create Database Entry
        $change_path = $this->file_system_service->storeChanges(
            $change_content,
            $file_id,
            $uuid->asString(),
            $version,
            $change_extension
        );
        $this->file_change_repository->create(
            $uuid,
            $version,
            $changes_object,
            $serverVersion,
            $change_path
        );

        // Return FileVersion object
        $fileVersion = new FileVersion($version, $created_at, $editor_id, $path, $uuid);
        return $fileVersion;
    }

    /**
     * @throws IOException
     */
    public function createFileTemplate(UploadResult $upload_result, string $title, string $description): string
    {
        $extension = pathinfo($upload_result->getName(), PATHINFO_EXTENSION);
        $type = File::determineDocType($extension, false);

        // If file extension not supported/recongnized by OnlyOffice
        if (empty($type)) {
            return "";
        }

        $path = $this->file_system_service->storeTemplate($upload_result, $type, $title, $description, $extension);

        return $path;
    }

    public function deleteFileTemplate(string $target, string $extension): bool
    {
        $type = File::determineDocType($extension, false);
        return $this->file_system_service->deleteTemplate($target, $extension, $type);
    }

    public function createFileDraft(string $name, string $extension, int $obj_id, string $new_file_id): string
    {
        return $this->file_system_service->storeDraft($name, $extension, $obj_id, $new_file_id);
    }

    public function createFileFromTemplate(string $new_title, string $template_path, int $obj_id, string $new_file_id)
    {
        return $this->file_system_service->createFileFromTemplate($new_title, $template_path, $obj_id, $new_file_id);
    }

    public function modifyFileTemplate(string $prevTitle, string $prevExtension, string $title, string $description): bool
    {
        $prevType = File::determineDocType($prevExtension, false);
        return $this->file_system_service->modifyTemplate($prevType, $prevTitle, $prevExtension, $title, $description);
    }

    /**
     * @param string $type text, table or presentation
     * @return FileTemplate[]
     */
    public function fetchTemplates(string $type): array
    {
        return $this->file_system_service->fetchTemplates($type);
    }

    public function fetchTemplate(string $target, string $extension): FileTemplate
    {
        $type = File::determineDocType($extension, false);
        return $this->file_system_service->fetchTemplate($target, $extension, $type);
    }

    public function createClone(int $child_id, int $parent_id): void
    {
        // create new file
        $uuid = new UUID();
        $parent_file = $this->file_repository->getFile($parent_id);
        if (is_null($parent_file)) {
            return;
        }
        $this->file_repository->create($uuid, $child_id, $parent_file->getTitle(), $parent_file->getFileType(), $parent_file->getMimeType());

        // clone file versions
        $parent_file_versions = $this->file_version_repository->getAllVersions($parent_file->getFileUuid());
        foreach ($parent_file_versions as $version) {
            $path = $this->file_system_service->storeVersionCopy($version, $uuid->asString(), $child_id);
            $created_at = new ilDateTime(time(), IL_CAL_UNIX);
            $this->file_version_repository->create(
                $uuid,
                $this->dic->user()->getId(),
                $created_at,
                $path,
                $version->getVersion()
            );
        }

        // clone file changes
        $parent_changes = $this->file_change_repository->getAllChanges($parent_file->getUuid()->asString());
        foreach ($parent_changes as $changes) {
            $path = $this->file_system_service->storeChangeCopy($changes, $uuid->asString(), $child_id);
            $this->file_change_repository->create(
                $uuid,
                $changes->getVersion(),
                $changes->getChangesObjectString(),
                $changes->getServerVersion(),
                $path
            );
        }

    }

    public function deleteFile(int $file_id): void
    {
        // remove physical structure
        $this->file_system_service->deletePath($file_id);
        $file = $this->file_repository->getFile($file_id);
        if (is_null($file)) {
            return;
        }
        $uuid = $file->getUuid();

        // delete File entry
        $query = 'DELETE FROM xono_file WHERE obj_id=' . $file_id . ';';
        $this->dic->database()->query($query);

        // delete FileVersion entries
        $query = 'DELETE FROM xono_file_version WHERE file_uuid="' . $uuid->asString() . '";';
        $this->dic->database()->query($query);

        // delete FileChange entries
        $query = 'DELETE FROM xono_file_change WHERE file_uuid="' . $uuid->asString() . '";';
        $this->dic->database()->query($query);

    }

    public function getAllVersions(int $object_id): array
    {
        $file = $this->file_repository->getFile($object_id);
        if (is_null($file)) {
            return [];
        }
        return $this->file_version_repository->getAllVersions($file->getFileUuid());
    }

    public function getAllChanges(string $uuid): array
    {
        return $this->file_change_repository->getAllChanges($uuid);
    }

    public function getChangeUrl(string $uuid, int $version): string
    {
        $file_change = $this->file_change_repository->getChange($uuid, $version);
        return $file_change->getChangesUrl();

    }

    public function getPreviousVersion(string $uuid, int $version): FileVersion
    {
        return $this->file_version_repository->getPreviousVersion($uuid, $version);
    }

    public function getFile(int $file_id): ?File
    {
        return $this->file_repository->getFile($file_id);
    }

    public function getLatestVersion(UUID $file_uuid): ?FileVersion
    {
        return $this->file_version_repository->getLatestVersion($file_uuid);
    }

    /**
     * Deletes all file data from storage and all related database entries
     * Should only be called when uninstalling the plugin
     */
    public function deleteAll(): void
    {
        $all_files = $this->file_repository->getAllFiles();
        /** @var File $file */
        foreach ($all_files as $file) {
            $this->deleteFile($file->getObjId());
        }
        FileAR::truncateDB();
        FileVersionAR::truncateDB();
        FileChangeAR::truncateDB();
    }

    /**
     * @throws ilDateTimeException
     */
    private function createNewFile(UUID $new_file_id, string $path): array
    {
        $created_at = new ilDateTime(time(), IL_CAL_UNIX);
        $version = $this->file_version_repository->create(
            $new_file_id,
            $this->dic->user()->getId(),
            $created_at,
            $path
        );

        // Create DB Entry for FileChange
        $changes = json_encode([
            "created" => rtrim($created_at->__toString(), '<br>'),
            "user" => [
                "id" => $this->dic->user()->getId(),
                "name" => $this->dic->user()->getFullname()
            ]
        ]);
        $this->file_change_repository->create(
            $new_file_id,
            $version,
            $changes,
            FileChangeRepository::DEFAULT_SERVER_VERSION,
            $path
        );
        return [$created_at, $version];
    }
}
