<?php

namespace srag\Plugins\OnlyOffice\StorageService\FileSystem;

use ILIAS\DI\Container;
use ILIAS\Filesystem\Exception\FileNotFoundException;
use ILIAS\Filesystem\Exception\IOException;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\FileUpload\Location;
use srag\Plugins\OnlyOffice\StorageService\DTO\FileTemplate;
use srag\Plugins\OnlyOffice\StorageService\DTO\FileVersion;
use ILIAS\Filesystem\Stream\Streams;
use srag\Plugins\OnlyOffice\StorageService\DTO\FileChange;

/**
 * Class FileSystemService
 * @package srag\Plugins\OnlyOffice\StorageService\FileSystem
 * @author  Theodor Truffer <theo@fluxlabs.ch>
 *          Sophie Pfister <sophie@fluxlabs.ch>
 */
class FileSystemService
{

    const BASE_PATH = '/only_office/';
    const BASE_TEMPLATE_PATH = '/only_office/templates/';
    protected Container $dic;

    /**
     * FileRepository constructor.
     * @param Container $dic
     */
    public function __construct(Container $dic)
    {
        $this->dic = $dic;
    }

    /**
     * @throws IOException
     */
    public function storeUploadResult(
        UploadResult $upload_result,
        int $obj_id,
        string $file_id,
        string $file_name = FileVersion::FIRST_VERSION
    ) : string {
        $ext = pathinfo($upload_result->getName(), PATHINFO_EXTENSION);
        $file_name .= '.' . $ext;

        $path = $this->createAndGetPath($obj_id, $file_id);
        $this->dic->upload()->moveOneFileTo(
            $upload_result,
            $path,
            Location::WEB,
            $file_name
        );
        $path .= $file_name;
        return $path;
    }

    public function storeNewVersionFromString(
        string $content,
        int $obj_id,
        string $uuid,
        int $version,
        string $extension
    ) : string {
        $path = $this->createAndGetPath($obj_id, $uuid) . $version . '.' . $extension;
        $this->dic->logger()->root()->info('Storing as: ' . $path);
        $stream = Streams::ofString($content);
        $web = $this->dic->filesystem()->web();
        $web->writeStream($path, $stream);
        return $path;
    }

    /**
     * Store a template from the config form
     * @throws IOException
     */
    public function storeTemplate(UploadResult $upload_result, string $type, string $title, string $description, string $extension) : string
    {
        // Define path and create it if it does not exist
        $path = self::BASE_TEMPLATE_PATH . $type . "/";

        if (!$this->dic->filesystem()->web()->hasDir($path)) {
            $this->dic->filesystem()->web()->createDir($path);
        }

        $file_name = $title . "." . $extension;
        $full_path = $path . $file_name;

        // delete the old template file if it already exists
        if ($this->dic->filesystem()->web()->has($full_path)) {
            $this->dic->filesystem()->web()->delete($full_path);
        }

        $this->dic->upload()->moveOneFileTo(
            $upload_result,
            $path,
            Location::WEB,
            $file_name
        );

        // Create a description in a separate folder, if available

        $this->generateTemplateDescription($description, $type, $title);

        return $full_path;
    }

    public function fetchTemplate(string $target, string $extension, string $type): ?FileTemplate
    {
        $path = self::BASE_TEMPLATE_PATH . $type . "/";
        $file_name = $target . "." . $extension;
        $full_path = $path . $file_name;

        if ($this->dic->filesystem()->web()->has($full_path)) {
            $extension = pathinfo($full_path, PATHINFO_EXTENSION);
            $title = pathinfo($full_path, PATHINFO_FILENAME);

            $template = new FileTemplate();
            $template->setTitle($title);
            $template->setType($type);
            $template->setPath($full_path);
            $template->setExtension($extension);

            $description_path = $path . "descriptions/" . $title . ".txt";

            try {
                $description_stream = $this->dic->filesystem()->web()->readStream($description_path);
                $template->setDescription($description_stream->getContents());
            } catch (FileNotFoundException $ex) {
                $template->setDescription("");
            }

            return $template;
        }
        return null;
    }

    public function fetchTemplates(string $type): array
    {
        $path = self::BASE_TEMPLATE_PATH . $type . "/";
        $converted_files = array();

        if ($this->dic->filesystem()->web()->hasDir($path)) {
            $files = $this->dic->filesystem()->web()->listContents($path);

            foreach ($files as $file) {
                $file = $file->getPath();
                $extension = pathinfo($file, PATHINFO_EXTENSION);

                if (empty($extension)) {
                    continue;
                }

                $title = pathinfo($file, PATHINFO_FILENAME);

                $converted_file = new FileTemplate();
                $converted_file->setTitle($title);
                $converted_file->setType($type);
                $converted_file->setPath($file);
                $converted_file->setExtension($extension);

                $description_path = $path . "descriptions/" . $title . ".txt";

                try {
                    $description_stream = $this->dic->filesystem()->web()->readStream($description_path);
                    $converted_file->setDescription($description_stream->getContents());
                } catch (FileNotFoundException $ex) {
                    $converted_file->setDescription("");
                }

                $converted_files[] = $converted_file;
            }
        }

        return $converted_files;
    }

    public function deleteTemplate(string $target, string $extension, string $type) : bool {
        $path = self::BASE_TEMPLATE_PATH . $type . "/";
        $file_name = $target . "." . $extension;
        $full_path = $path . $file_name;

        if ($this->dic->filesystem()->web()->has($full_path)) {
            // Delete template
            $this->dic->filesystem()->web()->delete($full_path);
            $this->deleteTemplateDescription($path, $target);

            return true;
        }

        return false;
    }

    public function modifyTemplate(string $type, string $prevTitle, string $extension, string $title, string $description) : bool
    {
        $path = self::BASE_TEMPLATE_PATH . $type . "/";
        $old_file_name = $prevTitle . "." . $extension;
        $full_old_path = $path . $old_file_name;

        $new_file_name = $title . "." . $extension;
        $full_new_path = $path . $new_file_name;

        if (!empty($title)) {
            if ($this->dic->filesystem()->web()->has($full_old_path) && $full_old_path !== $full_new_path) {
                $this->dic->filesystem()->web()->rename($full_old_path, $full_new_path);
            }
        }

        if (!empty($description)) {
            $this->deleteTemplateDescription($path, $prevTitle);
            $this->generateTemplateDescription($description, $type, $title);
        }

        return true;
    }

    /**
     * Store a draft
     * @throws IOException
     */
    public function storeDraft(string $name, string $extension, int $obj_id, string $new_file_id) : string
    {
        $path = $this->createAndGetPath($obj_id, $new_file_id);

        // Define path and create it if it does not exist
        if (!$this->dic->filesystem()->web()->hasDir($path)) {
            $this->dic->filesystem()->web()->createDir($path);
        }
        $file_name = $name . "." . $extension;
        $path .= $file_name;

        // delete the old draft file if it already exists
        if ($this->dic->filesystem()->web()->has($path)) {
            $this->dic->filesystem()->web()->delete($path);
        }
        // store the new draft file
        $stream = Streams::ofString("");
        $this->dic->filesystem()->web()->writeStream($path, $stream);
        return $path;

    }

    /**
     * Store a draft
     * @throws IOException
     */
    public function createFileFromTemplate(string $new_title, string $template_path, int $obj_id, string $new_file_id) : string
    {
        $extension = pathinfo($template_path, PATHINFO_EXTENSION);
        $name = pathinfo($template_path, PATHINFO_FILENAME);

        $path = $this->createAndGetPath($obj_id, $new_file_id);

        // Define path and create it if it does not exist
        if (!$this->dic->filesystem()->web()->hasDir($path)) {
            $this->dic->filesystem()->web()->createDir($path);
        }

        $file_name = $new_title . "." . $extension;
        $path .= $file_name;

        $this->dic->filesystem()->web()->copy($template_path, $path);

        return $path;
    }


    public function storeChanges(string $content, int $obj_id, string $uuid, int $version, string $extension) : string
    {
        $path = $this->createAndGetPath($obj_id, $uuid, true) . $version . '.' . $extension;
        $stream = Streams::ofString($content);
        $web = $this->dic->filesystem()->web();
        $web->writeStream($path, $stream);
        return $path;
    }

    public function storeVersionCopy(FileVersion $parent_version, string $uuid, int $file_id) : string
    {
        $parent_path = $parent_version->getUrl();
        $extension = pathinfo($parent_path, PATHINFO_EXTENSION);
        $child_path = $this->createAndGetPath($file_id, $uuid,
                false) . $parent_version->getVersion() . '.' . $extension;;
        $web = $this->dic->filesystem()->web();
        $web->copy($parent_path, $child_path);
        return $child_path;
    }

    public function storeChangeCopy(FileChange $parent_change, string $uuid, int $file_id) : string
    {
        $parent_path = $parent_change->getChangesUrl();
        $extension = pathinfo($parent_path, PATHINFO_EXTENSION);
        $child_path = $this->createAndGetPath($file_id, $uuid, true) . $parent_change->getVersion() . '.' . $extension;;
        $web = $this->dic->filesystem()->web();
        $web->copy($parent_path, $child_path);
        return $child_path;
    }

    public function deletePath(int $file_id): void
    {
        $path = self::BASE_PATH . $file_id;
        $web = $this->dic->filesystem()->web();
        $web->deleteDir($path);

    }

    /**
     * @throws IOException
     */
    protected function createAndGetPath(int $file_id, string $uuid, bool $isChange = false) : string
    {
        if (!$isChange) {
            $path = self::BASE_PATH . $file_id . DIRECTORY_SEPARATOR . $uuid . DIRECTORY_SEPARATOR;
        } else {
            $path = self::BASE_PATH . $file_id . DIRECTORY_SEPARATOR . $uuid . DIRECTORY_SEPARATOR . 'changes' . DIRECTORY_SEPARATOR;
        }

        if (!$this->dic->filesystem()->web()->hasDir($path)) {
            $this->dic->filesystem()->web()->createDir($path);
        }

        return $path;
    }

    /**
     * @throws IOException
     */
    private function generateTemplateDescription(string $description, string $type, string $title): void
    {
        if (!empty($description)) {
            $description_path = self::BASE_TEMPLATE_PATH . $type . "/descriptions/";

            if (!$this->dic->filesystem()->web()->hasDir($description_path)) {
                $this->dic->filesystem()->web()->createDir($description_path);
            }

            // Add file name to path
            $description_path .= $title . ".txt";

            $stream = Streams::ofString($description);
            $this->dic->filesystem()->web()->writeStream($description_path, $stream);
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

        if ($this->dic->filesystem()->web()->has($full_path_description)) {
            $this->dic->filesystem()->web()->delete($full_path_description);
        }
    }
}