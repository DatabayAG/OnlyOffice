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


namespace ILIAS\Plugin\OnlyOffice\Form\Property;

use ILIAS\Filesystem\Filesystem;
use ILIAS\FileUpload\Collection\EntryLockingStringMap;
use ILIAS\FileUpload\Collection\ImmutableMapWrapper;
use ILIAS\FileUpload\DTO\ProcessingStatus;
use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Enum\FileMode;
use ILIAS\Plugin\OnlyOffice\Form\ObjectSettingsForm;
use ILIAS\Plugin\OnlyOffice\StorageService\DTO\FileTemplate;
use ILIAS\Plugin\OnlyOffice\Upload\UploadHandler;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ilLanguage;
use ilObjectProperty;
use ilOnlyOfficePlugin;

class FileSettingProperty implements ilObjectProperty
{
    private ilOnlyOfficePlugin $plugin;
    private Filesystem $tmpFilesystem;

    /**
     * @param FileTemplate[] $templates
     */
    public function __construct(
        private readonly array             $templates = [],
        private readonly FileMode          $fileMode = FileMode::UPLOAD,
        private readonly ?UploadResult     $uploadResult = null,
        private readonly ?FileCreationType $fileCreationType = null,
        private readonly ?string           $fileTemplate = null,
    )
    {
        global $DIC;
        $this->tmpFilesystem = $DIC->filesystem()->temp();
        $this->plugin = ilOnlyOfficePlugin::getInstance();
    }

    public function toForm(
        ilLanguage   $language,
        FieldFactory $field_factory,
        Refinery     $refinery
    ): FormInput
    {
        $trafo = $refinery->custom()->transformation(
            function ($vs): self {
                $fileMode = FileMode::from($vs[0]);

                $uploadResult = null;
                $fileCreationType = null;
                $fileTemplate = null;
                switch ($fileMode) {
                    case FileMode::UPLOAD:
                        $filesystemPath = end($vs[1][ObjectSettingsForm::POST_VAR_FILE]);
                        $stream = $this->tmpFilesystem->readStream($filesystemPath);
                        $filePath = $stream->getMetadata("uri");

                        $uploadResult = new UploadResult(
                            basename($filePath),
                            $stream->getSize(),
                            mime_content_type($filePath),
                            new ImmutableMapWrapper(new EntryLockingStringMap()),
                            new ProcessingStatus(ProcessingStatus::OK, "Upload Ok"),
                            $filePath
                        );
                        $stream->close();

                        break;
                    case FileMode::CREATE:
                        $fileCreationType = FileCreationType::from($vs[1][ObjectSettingsForm::POST_VAR_FILE_CREATION_SETTING]);
                        break;

                    case FileMode::TEMPLATE:
                        $fileTemplate = $vs[1][ObjectSettingsForm::POST_VAR_FILE_TEMPLATE_SETTING];
                        break;
                }

                return new FileSettingProperty(
                    $this->templates,
                    $fileMode,
                    $uploadResult,
                    $fileCreationType,
                    $fileTemplate
                );
            }
        );

        $templateRadioOption = $field_factory->radio("");
        foreach ($this->templates as $template) {
            $type_translation = sprintf("form_template_%s", $template->getType());
            $templateRadioOption = $templateRadioOption->withOption(
                $template->getPath(),
                sprintf("%s %s", $template->getTitle(), $this->plugin->txt($type_translation)),
                !empty($template->getDescription()) ? $template->getDescription() : null
            );
        }

        $templateOption = $field_factory->group([
            ObjectSettingsForm::POST_VAR_FILE_TEMPLATE_SETTING => $templateRadioOption
        ], $this->plugin->txt('form_input_template'))->withRequired(true);

        $fileOptions = [
            ObjectSettingsForm::OPTION_SETTING_UPLOAD => $field_factory->group([
                ObjectSettingsForm::POST_VAR_FILE => $field_factory->file(
                    new UploadHandler(),
                    $this->plugin->txt('form_input_file')
                )->withRequired(true)
            ], $this->plugin->txt('form_input_upload_file')),
            ObjectSettingsForm::OPTION_SETTING_CREATE => $field_factory->group(
                [
                    ObjectSettingsForm::POST_VAR_FILE_CREATION_SETTING => $field_factory->radio(
                        "",
                    )->withRequired(true)
                        ->withOption("text", $this->plugin->txt('form_input_create_file_text'))
                        ->withOption("table", $this->plugin->txt('form_input_create_file_table'))
                        ->withOption("presentation", $this->plugin->txt('form_input_create_file_presentation'))
                ],
                $this->plugin->txt('form_input_create_file')
            ),
        ];
        if ($this->templates !== []) {
            $fileOptions[ObjectSettingsForm::OPTION_SETTING_TEMPLATE] = $templateOption;
        }

        return $field_factory->switchableGroup(
            $fileOptions,
            $this->plugin->txt('form_input_file'),
            $this->templates === [] ? $this->plugin->txt('form_input_template_no_templates') : null
        )
            ->withRequired(true)
            ->withAdditionalTransformation($trafo);
    }

    public function getFileMode(): FileMode
    {
        return $this->fileMode;
    }

    public function getUploadResult(): ?UploadResult
    {
        return $this->uploadResult;
    }

    public function getFileCreationType(): ?FileCreationType
    {
        return $this->fileCreationType;
    }

    public function getFileTemplate(): ?string
    {
        return $this->fileTemplate;
    }
}
