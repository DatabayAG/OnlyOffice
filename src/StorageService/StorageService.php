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

namespace ILIAS\Plugin\OnlyOffice\StorageService;

use ilDateTime;
use ilDateTimeException;
use ILIAS\Data\UUID\Factory as UUIDFactory;
use ILIAS\DI\Container;
use ILIAS\Filesystem\Exception\IOException;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Model\FileChange;
use ILIAS\Plugin\OnlyOffice\Model\FileVersion;
use ILIAS\Plugin\OnlyOffice\Repository\FileChangeRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\File;
use ILIAS\Plugin\OnlyOffice\Model\FileTemplate;
use ILIAS\Plugin\OnlyOffice\StorageService\FileSystem\FileSystemService;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileRepository;
use ILIAS\Plugin\OnlyOffice\Repository\FileVersionRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileAR;

class StorageService
{
    protected Container $dic;
    protected FileSystemService $file_system_service;
    private readonly UUIDFactory $uuidFactory;

    public function __construct(
        Container $dic,
        protected FileVersionRepository $file_version_repository,
        protected FileRepository $file_repository,
        protected FileChangeRepository $file_change_repository
    ) {
        $this->dic = $dic;
        $this->file_system_service = new FileSystemService($dic);
        $this->uuidFactory = new UUIDFactory();
    }

    /**
     * @throws IOException
     * @throws ilDateTimeException
     */
    public function createNewFileFromUpload(UploadResult $upload_result, int $obj_id): File
    {
        // Create DB Entries for File & FileVersion
        $new_file_id = $this->uuidFactory->uuid4();
        $path = $this->file_system_service->storeUploadResult($upload_result, $obj_id, $new_file_id->toString());
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $this->file_repository->create($new_file_id, $obj_id, $upload_result->getName(), $extension, $upload_result->getMimeType());

        $file_version = $this->createNewFileVersion($new_file_id, $path);
        $file = new File($new_file_id, $obj_id, $upload_result->getName(), $extension, $upload_result->getMimeType());
        return $file;
    }

    public function createNewFileFromDraft(string $title, string $extension, int $obj_id): File
    {
        $new_file_id = $this->uuidFactory->uuid4();
        $path = $this->createFileDraft(
            $title,
            $extension,
            $obj_id,
            $new_file_id->toString()
        );

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // Create DB Entries for File & FileVersion
        $this->file_repository->create($new_file_id, $obj_id, basename($path), $extension, $this->dic->filesystem()->web()->getMimeType($path));
        $file_version = $this->createNewFileVersion($new_file_id, $path);
        $file = new File($new_file_id, $obj_id, basename($path), $extension, $this->dic->filesystem()->web()->getMimeType($path));
        return $file;
    }

    public function createNewFileFromTemplate(string $title, string $template_path, int $obj_id): File
    {
        $new_file_id = $this->uuidFactory->uuid4();
        $extension = pathinfo($template_path, PATHINFO_EXTENSION);

        $path = $this->createFileFromTemplate(
            $title,
            $template_path,
            $obj_id,
            $new_file_id->toString()
        );

        // Create DB Entries for File & FileVersion
        $this->file_repository->create($new_file_id, $obj_id, basename($path), $extension, $this->dic->filesystem()->web()->getMimeType($path));
        $file_version = $this->createNewFileVersion($new_file_id, $path);
        return new File($new_file_id, $obj_id, basename($path), $extension, $this->dic->filesystem()->web()->getMimeType($path));
    }

    /**
     * @throws ilDateTimeException
     */
    public function updateFileFromUpload(
        string $file_content,
        int $file_id,
        Uuid $uuid,
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
            $uuid->toString(),
            $version,
            $file_extension
        );

        $file_version = new FileVersion(
            $uuid,
            $editor_id,
            $created_at,
            $path
        );

        $this->file_version_repository->create($file_version);

        //Store Changes and Create Database Entry
        $change_path = $this->file_system_service->storeChanges(
            $change_content,
            $file_id,
            $uuid->toString(),
            $version,
            $change_extension
        );
        $this->file_change_repository->create(new FileChange(
            $uuid,
            $version,
            $changes_object,
            $change_path,
            $serverVersion
        ));

        return $file_version;
    }

    /**
     * @throws IOException
     */
    public function createFileTemplate(UploadResult $upload_result, string $title, string $description): ?string
    {
        $extension = pathinfo($upload_result->getName(), PATHINFO_EXTENSION);
        $type = File::determineDocType($extension);

        // If file extension not supported/recongnized by OnlyOffice
        if (empty($type)) {
            return null;
        }

        $path = $this->file_system_service->storeTemplate($upload_result, $type, $title, $description, $extension);

        return $path;
    }

    public function deleteFileTemplate(string $target, string $extension): bool
    {
        $type = File::determineDocType($extension);
        return $this->file_system_service->deleteTemplate($target, $extension, $type);
    }

    public function createFileDraft(string $name, string $extension, int $obj_id, string $new_file_id): string
    {
        return $this->file_system_service->storeDraft($name, $extension, $obj_id, $new_file_id);
    }

    public function createFileFromTemplate(string $new_title, string $template_path, int $obj_id, string $new_file_id): string
    {
        return $this->file_system_service->createFileFromTemplate($new_title, $template_path, $obj_id, $new_file_id);
    }

    public function modifyFileTemplate(string $prevTitle, string $prevExtension, string $title, string $description): bool
    {
        $prevType = File::determineDocType($prevExtension);
        return $this->file_system_service->modifyTemplate($prevType, $prevTitle, $prevExtension, $title, $description);
    }

    /**
     * @return FileTemplate[]
     */
    public function fetchTemplates(FileCreationType $type): array
    {
        return $this->file_system_service->fetchTemplates($type);
    }

    public function fetchTemplate(string $target, string $extension): FileTemplate
    {
        $type = File::determineDocType($extension);
        return $this->file_system_service->fetchTemplate($target, $extension, $type);
    }

    public function createClone(int $child_id, int $parent_id): void
    {
        // create new file
        $uuid = $this->uuidFactory->uuid4();
        $parent_file = $this->file_repository->getFile($parent_id);
        if (is_null($parent_file)) {
            return;
        }
        $this->file_repository->create($uuid, $child_id, $parent_file->getTitle(), $parent_file->getFileType(), $parent_file->getMimeType());

        // clone file versions
        $parent_file_versions = $this->file_version_repository->readAllByUuid($parent_file->getFileUuid());
        foreach ($parent_file_versions as $file_version) {
            $path = $this->file_system_service->storeVersionCopy($file_version, $uuid->toString(), $child_id);
            $this->file_version_repository->create(new FileVersion(
                $uuid,
                $this->dic->user()->getId(),
                new ilDateTime(time(), IL_CAL_UNIX),
                $path,
                $file_version->getVersion()
            ));
        }

        // clone file changes
        $parent_changes = $this->file_change_repository->readAllByUuid($parent_file->getUuid());
        foreach ($parent_changes as $file_change) {
            $path = $this->file_system_service->storeChangeCopy($file_change, $uuid->toString(), $child_id);

            $this->file_change_repository->create(new FileChange(
                $uuid,
                $file_change->getVersion(),
                $file_change->getChangesObjectString(),
                $path,
                $file_change->getServerVersion(),
            ));
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
        $this->file_version_repository->deleteByFileUuid($uuid);

        // delete FileChange entries
        $this->file_change_repository->deleteByFileUuid($uuid);

    }

    /**
     * @return list<FileVersion>
     */
    public function getAllVersions(int $obj_id): array
    {
        $file = $this->file_repository->getFile($obj_id);
        if (is_null($file)) {
            return [];
        }
        return $this->file_version_repository->readAllByUuid($file->getFileUuid());
    }

    /**
     * @return array<int, FileChange>
     */
    public function getAllChanges(Uuid $uuid): array
    {
        return $this->file_change_repository->readAllByUuid($uuid);
    }

    public function getChangeUrl(Uuid $uuid, int $version): string
    {
        return $this->file_change_repository->readByUuid($uuid, $version)->getChangesUrl();

    }

    public function getPreviousVersion(Uuid $uuid, int $version): FileVersion
    {
        return $this->file_version_repository->readByUuid($uuid, $version - 1);
    }

    public function getFile(int $file_id): ?File
    {
        return $this->file_repository->getFile($file_id);
    }

    public function getLatestVersion(Uuid $file_uuid): ?FileVersion
    {
        return $this->file_version_repository->readLatestByUuid($file_uuid);
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
    }

    /**
     * @throws ilDateTimeException
     */
    private function createNewFileVersion(Uuid $new_file_id, string $path): FileVersion
    {
        $created_at = new ilDateTime(time(), IL_CAL_UNIX);

        $file_version = new FileVersion(
            $new_file_id,
            $this->dic->user()->getId(),
            $created_at,
            $path
        );

        $this->file_version_repository->create($file_version);

        // Create DB Entry for FileChange
        $changes = json_encode([
            "created" => rtrim($created_at->__toString(), '<br>'),
            "user" => [
                "id" => $this->dic->user()->getId(),
                "name" => $this->dic->user()->getFullname()
            ]
        ], JSON_THROW_ON_ERROR);
        $this->file_change_repository->create(new FileChange(
            $new_file_id,
            $file_version->getVersion(),
            $changes,
            $path,
            FileChange::DEFAULT_SERVER_VERSION
        ));

        return $file_version;
    }
}
