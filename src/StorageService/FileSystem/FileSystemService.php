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
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\FileUpload\FileUpload;
use ILIAS\FileUpload\Location;
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Model\FileChange;
use ILIAS\Plugin\OnlyOffice\Model\FileVersion;
use ILIAS\Plugin\OnlyOffice\Model\FileTemplate;
use ILIAS\Filesystem\Stream\Streams;
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

    /**
     * @throws IOException
     */
    public function storeUploadResult(UploadResult $upload_result
    ): ?ResourceIdentification {
        $stream = fopen($upload_result->getPath(), 'rb');
        return $this->resource_storage->manage()->stream(new Stream($stream), new FileStakeholder());
    }

    public function storeNewVersionFromString(
        string $content,
        int $obj_id,
        string $uuid,
        int $version,
        string $extension
    ): ResourceIdentification {
        return $this->resource_storage->manage()->stream(Streams::ofString($content), new FileStakeholder());
    }

    /**
     * Store a template from the config form
     * @throws IOException
     */
    public function storeTemplate(UploadResult $upload_result, FileCreationType $type, string $title, string $description, string $extension): string
    {
        // Define path and create it if it does not exist
        $path = self::BASE_TEMPLATE_PATH . $type->value . "/";

        if (!$this->web_file_system->hasDir($path)) {
            $this->web_file_system->createDir($path);
        }

        $file_name = $title . "." . $extension;
        $full_path = $path . $file_name;

        // delete the old template file if it already exists
        if ($this->web_file_system->has($full_path)) {
            $this->web_file_system->delete($full_path);
        }

        $this->upload->moveOneFileTo(
            $upload_result,
            $path,
            Location::WEB,
            $file_name
        );

        // Create a description in a separate folder, if available

        $this->generateTemplateDescription($description, $type, $title);

        return $full_path;
    }

    public function fetchTemplate(string $target, string $extension, FileCreationType $type): ?FileTemplate
    {
        $path = self::BASE_TEMPLATE_PATH . $type->value . "/";
        $file_name = $target . "." . $extension;
        $full_path = $path . $file_name;

        if ($this->web_file_system->has($full_path)) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection */
            $extension = pathinfo($full_path, PATHINFO_EXTENSION);
            $title = pathinfo($full_path, PATHINFO_FILENAME);


            $description = "";
            try {
                $description_stream = $this->web_file_system->readStream($path . "descriptions/" . $title . ".txt");
                $description = $description_stream->getContents();
            } catch (FileNotFoundException) {
            }

            return new FileTemplate(
                $title,
                $description,
                $extension,
                $type,
                $full_path
            );
        }
        return null;
    }

    public function fetchTemplates(FileCreationType $type): array
    {
        $path = self::BASE_TEMPLATE_PATH . $type->value . "/";
        $converted_files = [];

        if ($this->web_file_system->hasDir($path)) {
            $files = $this->web_file_system->listContents($path);

            foreach ($files as $file) {
                $file = $file->getPath();
                $extension = pathinfo($file, PATHINFO_EXTENSION);

                if (empty($extension)) {
                    continue;
                }

                $title = pathinfo($file, PATHINFO_FILENAME);

                $description = "";

                try {
                    $description_stream = $this->web_file_system->readStream($path . "descriptions/" . $title . ".txt");
                    $description = $description_stream->getContents();
                } catch (FileNotFoundException) {
                }

                $converted_files[] = new FileTemplate(
                    $title,
                    $description,
                    $extension,
                    $type,
                    $file
                );
            }
        }

        return $converted_files;
    }

    public function deleteTemplate(string $target, string $extension, FileCreationType $type): bool
    {
        $path = self::BASE_TEMPLATE_PATH . $type->value . "/";
        $file_name = $target . "." . $extension;
        $full_path = $path . $file_name;

        if ($this->web_file_system->has($full_path)) {
            // Delete template
            $this->web_file_system->delete($full_path);
            $this->deleteTemplateDescription($path, $target);

            return true;
        }

        return false;
    }

    public function modifyTemplate(FileCreationType $type, string $prev_title, string $extension, string $title, string $description): bool
    {
        $path = self::BASE_TEMPLATE_PATH . $type->value . "/";
        $old_file_name = $prev_title . "." . $extension;
        $full_old_path = $path . $old_file_name;

        $new_file_name = $title . "." . $extension;
        $full_new_path = $path . $new_file_name;

        if (
            !empty($title)
            && $this->web_file_system->has($full_old_path) && $full_old_path !== $full_new_path
        ) {
            $this->web_file_system->rename($full_old_path, $full_new_path);
        }

        if (!empty($description)) {
            $this->deleteTemplateDescription($path, $prev_title);
            $this->generateTemplateDescription($description, $type, $title);
        }

        return true;
    }

    /**
     * Store a draft
     * @throws IOException
     */
    public function storeDraft(string $name, string $extension, int $obj_id, string $new_file_id): string
    {
        $path = $this->createAndGetPath($obj_id, $new_file_id);

        // Define path and create it if it does not exist
        if (!$this->web_file_system->hasDir($path)) {
            $this->web_file_system->createDir($path);
        }
        $file_name = $name . "." . $extension;
        $path .= $file_name;

        // delete the old draft file if it already exists
        if ($this->web_file_system->has($path)) {
            $this->web_file_system->delete($path);
        }
        // store the new draft file
        $stream = Streams::ofString("");
        $this->web_file_system->writeStream($path, $stream);
        return $path;

    }

    /**
     * Store a draft
     * @throws IOException
     */
    public function createFileFromTemplate(string $new_title, string $template_path, int $obj_id, string $new_file_id): string
    {
        $extension = pathinfo($template_path, PATHINFO_EXTENSION);
        $name = pathinfo($template_path, PATHINFO_FILENAME);

        $path = $this->createAndGetPath($obj_id, $new_file_id);

        // Define path and create it if it does not exist
        if (!$this->web_file_system->hasDir($path)) {
            $this->web_file_system->createDir($path);
        }

        $file_name = $new_title . "." . $extension;
        $path .= $file_name;

        $this->web_file_system->copy($template_path, $path);

        return $path;
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

        return $this->resource_storage->manage()->clone($parent_rid);
    }

    public function storeChangeCopy(FileChange $parent_change, string $uuid, int $file_id): ?ResourceIdentification
    {
        $parent_rid = $parent_change->getRid();

        if (!$parent_rid) {
            return null;
        }

        return $this->resource_storage->manage()->clone($parent_rid);
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
