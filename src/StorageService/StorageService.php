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
use ILIAS\Plugin\OnlyOffice\Repository\FileTemplateRepository;
use ILIAS\Plugin\OnlyOffice\StorageService\FileSystem\FileSystemService;
use ILIAS\Data\UUID\Uuid;
use ILIAS\Plugin\OnlyOffice\Repository\FileVersionRepository;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;

class StorageService
{
    private readonly Container $dic;
    private readonly FileRepository $file_repository;
    private readonly FileVersionRepository $file_version_repository;
    private readonly FileChangeRepository $file_change_repository;
    private readonly FileTemplateRepository $file_template_repository;
    protected FileSystemService $file_system_service;
    private readonly UUIDFactory $uuid_factory;

    public function __construct(
        ?Container $dic = null,
        ?FileRepository $file_repository = null,
        ?FileVersionRepository $file_version_repository = null,
        ?FileChangeRepository $file_change_repository = null,
        ?FileTemplateRepository $file_template_repository = null,
        ?FileSystemService $file_system_service = null,
        ?UUIDFactory $uuid_factory = null
    ) {
        global $DIC;
        $this->dic = $dic ?? $DIC;
        $this->file_repository = $file_repository ?? new FileRepository($this->dic->database());
        $this->file_version_repository = $file_version_repository ?? new FileVersionRepository($this->dic->database());
        $this->file_change_repository = $file_change_repository ?? new FileChangeRepository($this->dic->database());
        $this->file_template_repository = $file_template_repository ?? new FileTemplateRepository($this->dic->database());
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
        $rid = $this->file_system_service->createFileFromUpload($upload_result);

        $file = new File(
            $obj_id,
            $new_file_id,
            $upload_result->getName(),
            pathinfo($upload_result->getPath(), PATHINFO_EXTENSION),
            $upload_result->getMimeType()
        );

        $this->file_repository->create($file);
        $file_version = $this->createNewFileVersion($new_file_id, $rid);

        return $file;
    }

    public function createNewFileFromDraft(string $title, FileCreationType $file_type, int $obj_id): File
    {
        $extension = $file_type->toDocumentType();
        $new_file_id = $this->uuid_factory->uuid4();
        //Empty string will cause the new file to not be opened with the correct application,
        //when immediately downloaded because it's mime type will not be set correctly yet.
        //It may be a good idea to set anything else than empty to avoid this.
        $rid = $this->file_system_service->createFileFromString("");

        $file = new File(
            $obj_id,
            $new_file_id,
            "$title.$extension",
            $extension,
            $file_type->toMimeType()
        );

        $this->file_repository->create($file);
        $file_version = $this->createNewFileVersion($new_file_id, $rid);

        return $file;
    }

    public function createNewFileFromTemplate(string $title, $template_id, int $obj_id): ?File
    {
        $template = $this->file_template_repository->read($template_id);

        if (!$template) {
            return null;
        }

        $new_file_id = $this->uuid_factory->uuid4();
        $rid = $this->file_system_service->copyFile($template->getRid());

        $file = new File(
            $obj_id,
            $new_file_id,
            "$title.{$template->getExtension()}",
            $template->getExtension(),
            $this->file_system_service->getMimeType($rid)
        );

        $this->file_repository->create($file);
        $file_version = $this->createNewFileVersion($new_file_id, $rid);

        return $file;
    }

    /**
     * @throws ilDateTimeException
     */
    public function updateFileFromUpload(
        string $file_content,
        Uuid $uuid,
        int $editor_id,
        string $changes_object,
        string $server_version,
        string $change_content,
    ): FileVersion {

        // Store FileVersion and Create Database Entry
        $created_at = new ilDateTime(time(), IL_CAL_UNIX);
        $version = $this->getLatestVersion($uuid)->getVersion() + 1;
        echo $version;
        $rid = $this->file_system_service->createFileFromString($file_content);

        $file_version = new FileVersion(
            $uuid,
            $editor_id,
            $created_at,
            $rid
        );

        $this->file_version_repository->create($file_version);

        //Store Changes and Create Database Entry
        $rid = $this->file_system_service->createFileFromString($change_content);
        $this->file_change_repository->create(new FileChange(
            $uuid,
            $version,
            $changes_object,
            $rid,
            $server_version
        ));

        return $file_version;
    }

    /**
     * @throws IOException
     */
    public function createFileTemplate(UploadResult $upload_result, string $title, string $description): ?FileTemplate
    {
        $extension = pathinfo($upload_result->getName(), PATHINFO_EXTENSION);

        $type = FileCreationType::determineDocType($extension);

        // If file extension not supported/recongnized by OnlyOffice
        if ($type === FileCreationType::NONE) {
            return null;
        }

        $rid = $this->file_system_service->createFileFromUpload($upload_result);

        $file_template = new FileTemplate(
            $title,
            $description,
            $extension,
            $type,
            $rid
        );

        if ($this->file_template_repository->create($file_template)) {
            return $file_template;
        }

        return null;
    }

    public function deleteFileTemplate(int $template_id): bool
    {
        $file_template = $this->fetchTemplate($template_id);

        if (!$file_template) {
            return false;
        }

        $file_deleted =  $this->file_system_service->deleteFile($file_template->getRid());
        if (!$file_deleted) {
            return false;
        }

        return $this->file_template_repository->delete($file_template);
    }

    public function modifyTemplate(FileTemplate $file_template, ?UploadResult $upload_result = null): bool
    {
        if ($upload_result) {
            $upload_success = $this->file_system_service->replaceFileWithUpload($file_template->getRid(), $upload_result);
        } else {
            $upload_success = true;
        }

        if (!$upload_success) {
            return false;
        }

        $this->file_template_repository->update($file_template);
        return true;
    }

    /**
     * @return FileTemplate[]
     */
    public function fetchTemplates(FileCreationType $type): array
    {
        return $this->file_template_repository->readAll([$type]);
    }

    public function fetchTemplate(int $template_id): ?FileTemplate
    {
        return $this->file_template_repository->read($template_id);
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
            $rid = $this->file_system_service->storeVersionCopy($file_version, $uuid->toString(), $child_id);
            $this->file_version_repository->create(new FileVersion(
                $uuid,
                $this->dic->user()->getId(),
                new ilDateTime(time(), IL_CAL_UNIX),
                $rid,
                $file_version->getVersion()
            ));
        }

        // clone file changes
        $parent_changes = $this->file_change_repository->readAllByUuid($parent_file->getUuid());
        foreach ($parent_changes as $file_change) {
            $rid = $this->file_system_service->storeChangeCopy($file_change, $uuid->toString(), $child_id);

            $this->file_change_repository->create(new FileChange(
                $uuid,
                $file_change->getVersion(),
                $file_change->getChangesObjectString(),
                $rid,
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

    public function getChangeUrl(FileVersion $file_version): ?string
    {
        $file_change = $this->file_change_repository->readByUuid($file_version->getFileUuid(), $file_version->getVersion());
        if (!$file_change) {
            return null;
        }
        return $this->file_system_service->getChangeUrl($file_change);

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
    private function createNewFileVersion(Uuid $new_file_id, ResourceIdentification $rid): FileVersion
    {
        $created_at = new ilDateTime(time(), IL_CAL_UNIX);

        $file_version = new FileVersion(
            $new_file_id,
            $this->dic->user()->getId(),
            $created_at,
            $rid
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
            $rid,
            FileChange::DEFAULT_SERVER_VERSION
        ));

        return $file_version;
    }
}
