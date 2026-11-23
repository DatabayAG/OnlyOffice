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

use Exception;
use ILIAS\DI\Container;
use ILIAS\Filesystem\Filesystem;
use ILIAS\Filesystem\Stream\Stream;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\FileUpload\FileUpload;
use ILIAS\Plugin\OnlyOffice\Stakeholder\FileStakeholder;
use ILIAS\ResourceStorage\Consumer\DownloadConsumer;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;
use ILIAS\ResourceStorage\Policy\FileNamePolicyException;
use ILIAS\ResourceStorage\Resource\ResourceNotFoundException;
use ILIAS\ResourceStorage\Services;
use ilLogger;

class FileSystemService
{
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

    public function createFileFromStream(Stream $stream): ?ResourceIdentification
    {
        try {
            return $this->resource_storage->manage()->stream($stream, new FileStakeholder());
        } catch (FileNamePolicyException $ex) {
            $this->logger->error(sprintf(
                "Error occurred trying to create file from stream. Ex.: %s",
                $ex->getMessage()
            ));
            return null;
        }
    }

    public function createFileFromUpload(UploadResult $upload_result): ?ResourceIdentification
    {
        $stream = fopen($upload_result->getPath(), 'rb');
        if (!is_resource($stream)) {
            $this->logger->error("Error occurred trying to create file from upload. Upload could not be opened.");
            return null;
        }
        return $this->createFileFromStream(new Stream($stream));
    }

    public function createFileFromString(string $content): ?ResourceIdentification
    {
        return $this->createFileFromStream(Streams::ofString($content));
    }

    public function deleteFile(ResourceIdentification $rid): bool
    {
        try {
            $this->resource_storage->manage()->remove($rid, new FileStakeholder());
        } catch (ResourceNotFoundException $ex) {
            $this->logger->error(sprintf(
                "Deleting file with rid '%s' failed because resource could not be found. Ex.: %s",
                $rid->serialize(),
                $ex->getMessage()
            ));
            return true;
        }
        return !$this->resource_storage->manage()->find($rid->serialize());
    }

    public function replaceFile(ResourceIdentification $rid, Stream $stream): bool
    {
        try {
            $revision = $this->resource_storage->manage()->replaceWithStream($rid, $stream, new FileStakeholder());
        } catch (ResourceNotFoundException $ex) {
            $this->logger->error(sprintf(
                "Replacing file with rid '%s' failed because resource could not be found. Ex.: %s",
                $rid,
                $ex->getMessage()
            ));
            return false;
        } catch (FileNamePolicyException|Exception $ex) {
            $this->logger->error(sprintf(
                "Error occurred trying to replace file with rid '%s'. Ex.: %s",
                $rid->serialize(),
                $ex->getMessage()
            ));
            return false;
        }
        return $revision->isAvailable();
    }

    public function copyFile(ResourceIdentification $rid): ?ResourceIdentification
    {
        try {
            return $this->resource_storage->manage()->clone($rid);
        } catch (ResourceNotFoundException $ex) {
            $this->logger->error(sprintf(
                "Error occurred trying to copy file with rid '%s' because the resource was not found. Ex.: %s",
                $rid->serialize(),
                $ex->getMessage()
            ));
            return null;
        }
    }

    public function getMimeType(ResourceIdentification $rid): string
    {
        try {
            $stream = $this->resource_storage->consume()->stream($rid);
        } catch (ResourceNotFoundException $ex) {
            $this->logger->error(sprintf(
                "Error occurred trying to determine mime_type of file with rid '%s' because the resource was not found. Ex.: %s",
                $rid->serialize(),
                $ex->getMessage()
            ));
            return "";
        }
        $resource = $stream->getStream()->detach();
        /** @noinspection PhpComposerExtensionStubsInspection */
        $mime_type = mime_content_type($resource);
        fclose($resource);
        return $mime_type;
    }

    public function replaceFileWithUpload(ResourceIdentification $rid, UploadResult $upload_result): bool
    {
        $stream = fopen($upload_result->getPath(), 'rb');
        return $this->replaceFile($rid, new Stream($stream));
    }

    public function getSource(ResourceIdentification $rid): ?string
    {
        try {
            return $this->resource_storage->consume()->src($rid)->getSrc();
        } catch (ResourceNotFoundException $ex) {
            $this->logger->error(sprintf(
                "Error occurred trying to get source for file with rid '%s' because the resource was not found. Ex.: %s",
                $rid->serialize(),
                $ex->getMessage()
            ));
            return "";
        }
    }

    public function getDownload(ResourceIdentification $rid): ?DownloadConsumer
    {
        try {
            return $this->resource_storage->consume()->download($rid);
        } catch (ResourceNotFoundException $ex) {
            $this->logger->error(sprintf(
                "Error occurred trying to get source for file with rid '%s' because the resource was not found. Ex.: %s",
                $rid->serialize(),
                $ex->getMessage()
            ));

            return null;
        }
    }
}
