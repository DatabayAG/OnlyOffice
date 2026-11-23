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
use ILIAS\ResourceStorage\Consumer\DownloadConsumer;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;
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

    public function getSource(ResourceIdentification $rid): ?string
    {
        return $this->resource_storage->consume()->src($rid)->getSrc();
    }

    public function getDownload(ResourceIdentification $rid): DownloadConsumer
    {
        return $this->resource_storage->consume()->download($rid);
    }
}
