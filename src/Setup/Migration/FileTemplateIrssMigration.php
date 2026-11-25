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

namespace ILIAS\Plugin\OnlyOffice\Setup\Migration;

use FilesystemIterator;
use ilDatabaseUpdateStepsExecutedObjective;
use ilDBConstants;
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Setup\Database\ExistingInstallationUpdateSteps;
use ILIAS\Plugin\OnlyOffice\Setup\Database\UpdateSteps;
use ILIAS\Plugin\OnlyOffice\Stakeholder\FileStakeholder;
use ILIAS\Setup\CLI\IOWrapper;
use ILIAS\Setup\Environment;
use ILIAS\Setup\Migration;
use ilResourceStorageMigrationHelper;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

class FileTemplateIrssMigration implements Migration
{
    public const string BASE_TEMPLATE_PATH = "/only_office/templates/";
    public const string DESCRIPTIONS_DIR_NAME = "descriptions";

    public const int NUMBER_OF_STEPS = 5;

    public const string TABLE_NAME = "xono_file_template";
    private ilResourceStorageMigrationHelper $helper;
    private ?IOWrapper $io = null;


    public function getLabel(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }

    public function getDefaultAmountOfStepsPerRun(): int
    {
        return self::NUMBER_OF_STEPS;
    }

    public function getPreconditions(Environment $environment): array
    {
        return [
            ...ilResourceStorageMigrationHelper::getPreconditions(),
            new ilDatabaseUpdateStepsExecutedObjective(new ExistingInstallationUpdateSteps()),
            new ilDatabaseUpdateStepsExecutedObjective(new UpdateSteps())
        ];
    }

    public function prepare(Environment $environment): void
    {
        $this->helper = new ilResourceStorageMigrationHelper(
            new FileStakeholder(),
            $environment
        );

        $io = $environment->getResource(Environment::RESOURCE_ADMIN_INTERACTION);
        if ($io instanceof IOWrapper) {
            $this->io = $io;
        }
    }

    public function step(Environment $environment): void
    {
        $db = $this->helper->getDatabase();
        $dir = new RecursiveDirectoryIterator(
            ILIAS_ABSOLUTE_PATH . "/" . ILIAS_WEB_DIR . "/" . CLIENT_ID . self::BASE_TEMPLATE_PATH,
            FilesystemIterator::SKIP_DOTS
        );

        /** @var array<string, array{
         *     content_file: SplFileInfo,
         *     description: string,
         *     description_file: ?SplFileInfo
         * }> $file_map
         */
        $file_map = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator($dir) as $file) {
            $file_name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            if ($file->getExtension() === "txt" || basename($file->getPath()) === self::DESCRIPTIONS_DIR_NAME) {
                if (isset($file_map[$file_name])) {
                    $file_map[$file_name]["description"] = $file->isReadable() ? (file_get_contents($file->getRealPath()) ?? "") : "";
                    $file_map[$file_name]["description_file"] = $file;
                }
                continue;
            }

            $access_error = false;

            if (!$file->isReadable()) {
                $this->error("Missing permission to read file '{$file->getRealPath()}'. Please check permissions before re-running migration");
                $access_error = true;
            }

            if (!$file->isWritable()) {
                $this->error("Missing permission to write file '{$file->getRealPath()}'. Please check permissions before re-running migration");
                $access_error = true;
            }

            if ($access_error) {
                continue;
            }

            $file_map[$file_name] = [
                "file" => $file,
                "description" => "",
                "description_file" => null
            ];
        }

        /**
         * @var array{
         *      content_file: SplFileInfo,
         *      description: string,
         *      description_file: ?SplFileInfo
         *  } $file_data
         */
        foreach ($file_map as $file_name => $file_data) {
            $file = $file_data["file"];
            $description = $file_data["description"];
            $description_file = $file_data["description_file"];

            $rid = $this->helper->movePathToStorage(
                $file->getRealPath(),
                $this->helper->getStakeholder()->getOwnerOfNewResources(),
                null,
                null,
                true
            );

            if (!$rid) {
                $this->error(sprintf(
                    "Error occurred trying to migrate template file '%s' (path: '%s'). Original file was not deleted!",
                    $file->getFilename(),
                    $file->getPath()
                ));
                continue;
            }

            $id = $db->nextId(self::TABLE_NAME);

            $success = $db->insert(
                self::TABLE_NAME,
                [
                        "id" => [ilDBConstants::T_INTEGER, $id],
                        "title" => [ilDBConstants::T_TEXT, $file_name],
                        "description" => [ilDBConstants::T_CLOB, $description],
                        "type" => [ilDBConstants::T_TEXT, FileCreationType::determineDocType($file->getExtension())->value],
                        "extension" => [ilDBConstants::T_TEXT, $file->getExtension()],
                        "rid" => [ilDBConstants::T_CLOB, $rid->serialize()],
                    ]
            ) === 1;

            if ($success) {
                if (!unlink($file->getRealPath())) {
                    $this->error(sprintf(
                        "Error occurred trying to delete original file '%s' in path '%s'. Manual deletion required. Re-run of migration may lead to duplicate templates!",
                        $file->getFilename(),
                        $file->getPath(),
                    ));
                }
                if ($description_file && !unlink($description_file->getRealPath())) {
                    $this->error(sprintf(
                        "Error occurred trying to delete original description file '%s' in path '%s'. Manual deletion required. Re-run of migration may lead to duplicate templates!",
                        $description_file->getFilename(),
                        $description_file->getPath(),
                    ));
                }

                $this->inform(sprintf(
                    "Successfully migrated template file '%s' (path: '%s'). New ID: '%s', new rid: '%s'",
                    $file->getFilename(),
                    $file->getPath(),
                    $id,
                    $rid->serialize()
                ));
            }
        }
    }

    public function getRemainingAmountOfSteps(): int
    {
        $count = 0;

        $dir = new RecursiveDirectoryIterator(
            ILIAS_ABSOLUTE_PATH . "/" . ILIAS_WEB_DIR . "/" . CLIENT_ID . self::BASE_TEMPLATE_PATH,
            FilesystemIterator::SKIP_DOTS
        );

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator($dir) as $file) {
            if ($file->getExtension() === "txt" || basename($file->getPath()) === self::DESCRIPTIONS_DIR_NAME) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    private function inform(string $text, bool $force = false): void
    {
        if ($this->io === null || (!$force && !$this->io->isVerbose())) {
            return;
        }

        $this->io->inform($text);
    }

    private function error(string $text): void
    {
        if ($this->io === null) {
            return;
        }

        $this->io->error($text);
    }
}
