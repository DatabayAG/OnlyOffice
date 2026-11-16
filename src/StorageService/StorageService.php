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
use ILIAS\Plugin\OnlyOffice\Model\File;
use ILIAS\Plugin\OnlyOffice\Model\FileChange;
use ILIAS\Plugin\OnlyOffice\Model\FileVersion;
use ILIAS\Plugin\OnlyOffice\Repository\FileChangeRepository;
use ILIAS\Plugin\OnlyOffice\Repository\FileRepository;
use ILIAS\Plugin\OnlyOffice\Model\FileTemplate;
use ILIAS\Plugin\OnlyOffice\StorageService\FileSystem\FileSystemService;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Plugin\OnlyOffice\Repository\FileVersionRepository;

class StorageService
{
    private readonly Container $dic;
    private readonly FileRepository $file_repository;
    private readonly FileVersionRepository $file_version_repository;
    private readonly FileChangeRepository $file_change_repository;
    protected FileSystemService $file_system_service;
    private readonly UUIDFactory $uuid_factory;

    public function __construct(
        ?Container $dic = null,
        ?FileRepository $file_repository = null,
        ?FileVersionRepository $file_version_repository = null,
        ?FileChangeRepository $file_change_repository = null,
        ?FileSystemService $file_system_service = null,
        ?UUIDFactory $uuid_factory = null
    ) {
        global $DIC;
        $this->dic = $dic ?? $DIC;
        $this->file_repository = $file_repository ?? new FileRepository($this->dic->database());
        $this->file_version_repository = $file_version_repository ?? new FileVersionRepository($this->dic->database());
        $this->file_change_repository = $file_change_repository ?? new FileChangeRepository($this->dic->database());
        $this->file_system_service = $file_system_service ?? new FileSystemService($this->dic);
        $this->uuid_factory = $uuid_factory ?? new UUIDFactory();
    }

    /**
     * @throws IOException
     * @throws ilDateTimeException
     */
    public function createNewFileFromUpload(UploadResult $upload_result, int $obj_id): File
    {
        // Create DB Entries for File & FileVersion
        $new_file_id = $this->uuid_factory->uuid4();
        $path = $this->file_system_service->storeUploadResult($upload_result, $obj_id, $new_file_id->toString());
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $file = new File(
            $obj_id,
            $new_file_id,
            $upload_result->getName(),
            $extension,
            $upload_result->getMimeType()
        );

        $this->file_repository->create($file);
        $file_version = $this->createNewFileVersion($new_file_id, $path);

        return $file;
    }

    public function createNewFileFromDraft(string $title, string $extension, int $obj_id): File
    {
        $new_file_id = $this->uuid_factory->uuid4();
        $path = $this->createFileDraft(
            $title,
            $extension,
            $obj_id,
            $new_file_id->toString()
        );

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $file = new File(
            $obj_id,
            $new_file_id,
            basename($path),
            $extension,
            $this->dic->filesystem()->web()->getMimeType($path)
        );

        $this->file_repository->create($file);
        $file_version = $this->createNewFileVersion($new_file_id, $path);

        return $file;
    }

    public function createNewFileFromTemplate(string $title, string $template_path, int $obj_id): File
    {
        $new_file_id = $this->uuid_factory->uuid4();
        $extension = pathinfo($template_path, PATHINFO_EXTENSION);

        $path = $this->createFileFromTemplate(
            $title,
            $template_path,
            $obj_id,
            $new_file_id->toString()
        );

        $file = new File(
            $obj_id,
            $new_file_id,
            basename($path),
            $extension,
            $this->dic->filesystem()->web()->getMimeType($path)
        );

        $this->file_repository->create($file);
        $file_version = $this->createNewFileVersion($new_file_id, $path);

        return $file;
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
        string $server_version,
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
            $server_version
        ));

        return $file_version;
    }

    /**
     * @throws IOException
     */
    public function createFileTemplate(UploadResult $upload_result, string $title, string $description): ?string
    {
        $extension = pathinfo($upload_result->getName(), PATHINFO_EXTENSION);

        $type = FileCreationType::determineDocType($extension);

        // If file extension not supported/recongnized by OnlyOffice
        if ($type === FileCreationType::NONE) {
            return null;
        }

        $path = $this->file_system_service->storeTemplate($upload_result, $type, $title, $description, $extension);

        return $path;
    }

    public function deleteFileTemplate(string $target, string $extension): bool
    {
        $type = FileCreationType::determineDocType($extension);
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

    public function modifyFileTemplate(string $prev_title, string $prev_extension, string $title, string $description): bool
    {
        $prev_type = FileCreationType::determineDocType($prev_extension);
        return $this->file_system_service->modifyTemplate($prev_type, $prev_title, $prev_extension, $title, $description);
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
        $type = FileCreationType::determineDocType($extension);
        return $this->file_system_service->fetchTemplate($target, $extension, $type);
    }

    public function createClone(int $child_id, int $parent_id): void
    {
        // create new file
        $uuid = $this->uuid_factory->uuid4();
        $parent_file = $this->file_repository->read($parent_id);
        if (!$parent_file) {
            return;
        }

        $this->file_repository->create(new File(
            $child_id,
            $uuid,
            $parent_file->getTitle(),
            $parent_file->getFileType(),
            $parent_file->getMimeType()
        ));

        // clone file versions
        $parent_file_versions = $this->file_version_repository->readAllByUuid($parent_file->getUuid());
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
        $file = $this->file_repository->read($file_id);
        if (!$file) {
            return;
        }
        $uuid = $file->getUuid();

        $this->file_repository->deleteById($file_id);
        $this->file_version_repository->deleteByFileUuid($uuid);
        $this->file_change_repository->deleteByFileUuid($uuid);
    }

    /**
     * @return list<FileVersion>
     */
    public function getAllVersions(int $obj_id): array
    {
        $file = $this->file_repository->read($obj_id);
        if (!$file) {
            return [];
        }
        return $this->file_version_repository->readAllByUuid($file->getUuid());
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
        return $this->file_change_repository->readByUuid($uuid, $version)->getUrl();

    }

    public function getPreviousVersion(Uuid $uuid, int $version): FileVersion
    {
        return $this->file_version_repository->readByUuid($uuid, $version - 1);
    }

    public function getFile(int $file_id): ?File
    {
        return $this->file_repository->read($file_id);
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
        foreach ($this->file_repository->readAll() as $file) {
            $this->deleteFile($file->getObjId());
        }
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
