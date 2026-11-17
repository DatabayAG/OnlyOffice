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

namespace ILIAS\Plugin\OnlyOffice\StorageService\FileSystem;

use ILIAS\DI\Container;
use ILIAS\Filesystem\Exception\FileNotFoundException;
use ILIAS\Filesystem\Exception\IOException;
use ILIAS\Filesystem\Filesystem;
use ILIAS\Filesystem\Stream\Stream;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\FileUpload\FileUpload;
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Model\FileChange;
use ILIAS\Plugin\OnlyOffice\Model\FileVersion;
use ILIAS\Plugin\OnlyOffice\Stakeholder\FileStakeholder;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Services;
use ilLogger;

class FileSystemService
{
    public const string BASE_PATH = '/only_office/';
    public const string BASE_TEMPLATE_PATH = '/only_office/templates/';
    private Filesystem $web_file_system;
    private ilLogger $logger;
    private FileUpload $upload;
    private Services $resource_storage;

    public function __construct(private readonly Container $dic)
    {
        $this->upload = $this->dic->upload();
        $this->web_file_system = $this->dic->filesystem()->web();
        $this->resource_storage = $this->dic->resourceStorage();
        $this->logger = $this->dic->logger()->root();
    }

    public function createFileFromStream(Stream $stream): ResourceIdentification {
        return $this->resource_storage->manage()->stream($stream, new FileStakeholder());
    }

    public function createFileFromUpload(UploadResult $upload_result): ?ResourceIdentification
    {
        $stream = fopen($upload_result->getPath(), 'rb');
        return $this->createFileFromStream(new Stream($stream));
    }

    public function createFileFromString(string $content): ResourceIdentification {
        return $this->createFileFromStream(Streams::ofString($content));
    }

    public function deleteFile(ResourceIdentification $rid): bool
    {
        $this->resource_storage->manage()->remove($rid, new FileStakeholder());
        return !$this->resource_storage->manage()->find($rid->serialize());
    }

    public function replaceFile(ResourceIdentification $rid, Stream $stream): bool
    {
        $revision = $this->resource_storage->manage()->replaceWithStream($rid, $stream, new FileStakeholder());
        return $revision->isAvailable();
    }

    public function copyFile(ResourceIdentification $rid): ResourceIdentification
    {
        return $this->resource_storage->manage()->clone($rid);
    }

    public function getMimeType(ResourceIdentification $rid): string
    {
        $stream = $this->resource_storage->consume()->stream($rid);
        $resource = $stream->getStream()->detach();
        $mime_type = mime_content_type($resource);
        fclose($resource);
        return $mime_type;
    }

    public function replaceFileWithUpload(ResourceIdentification $rid, UploadResult $upload_result): bool
    {
        $stream = fopen($upload_result->getPath(), 'rb');
        return $this->replaceFile($rid, new Stream($stream));
    }


    public function storeChanges(string $content, int $obj_id, string $uuid, int $version, string $extension): string
    {
        $path = $this->createAndGetPath($obj_id, $uuid, true) . $version . '.' . $extension;
        $stream = Streams::ofString($content);
        $web = $this->web_file_system;
        $web->writeStream($path, $stream);
        return $path;
    }

    public function storeVersionCopy(FileVersion $parent_version, string $uuid, int $file_id): ?ResourceIdentification
    {
        $parent_rid = $parent_version->getRid();

        if (!$parent_rid) {
            return null;
        }

        return $this->copyFile($parent_rid);
    }

    public function storeChangeCopy(FileChange $parent_change, string $uuid, int $file_id): ?ResourceIdentification
    {
        $parent_rid = $parent_change->getRid();

        if (!$parent_rid) {
            return null;
        }

        return $this->copyFile($parent_rid);
    }

    public function deletePath(int $file_id): void
    {
        $path = self::BASE_PATH . $file_id;
        $web = $this->web_file_system;
        $web->deleteDir($path);

    }

    /**
     * @throws IOException
     */
    protected function createAndGetPath(int $file_id, string $uuid, bool $is_change = false): string
    {
        if (!$is_change) {
            $path = self::BASE_PATH . $file_id . DIRECTORY_SEPARATOR . $uuid . DIRECTORY_SEPARATOR;
        } else {
            $path = self::BASE_PATH . $file_id . DIRECTORY_SEPARATOR . $uuid . DIRECTORY_SEPARATOR . 'changes' . DIRECTORY_SEPARATOR;
        }

        if (!$this->web_file_system->hasDir($path)) {
            $this->web_file_system->createDir($path);
        }

        return $path;
    }

    /**
     * @throws IOException
     */
    private function generateTemplateDescription(string $description, FileCreationType $type, string $title): void
    {
        if (!empty($description)) {
            $description_path = self::BASE_TEMPLATE_PATH . $type->value . "/descriptions/";

            if (!$this->web_file_system->hasDir($description_path)) {
                $this->web_file_system->createDir($description_path);
            }

            // Add file name to path
            $description_path .= $title . ".txt";

            $stream = Streams::ofString($description);
            $this->web_file_system->writeStream($description_path, $stream);
        }
    }

    /**
     * @throws FileNotFoundException
     * @throws IOException
     */
    private function deleteTemplateDescription(string $path, string $target): void
    {
        // Delete template description
        $full_path_description = $path . "descriptions/" . $target . ".txt";

        if ($this->web_file_system->has($full_path_description)) {
            $this->web_file_system->delete($full_path_description);
        }
    }

    public function getChangeUrl(FileChange $file_change): ?string
    {
        if (!$file_change->getRid()) {
            return null;
        }

        return $this->resource_storage->consume()->src($file_change->getRid())->getSrc();
    }
}
