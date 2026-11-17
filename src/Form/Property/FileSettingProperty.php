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
use ILIAS\ILIASObject\Properties\Property;
use ILIAS\Plugin\OnlyOffice\Enum\FileCreationType;
use ILIAS\Plugin\OnlyOffice\Enum\FileMode;
use ILIAS\Plugin\OnlyOffice\Form\ObjectSettingsForm;
use ILIAS\Plugin\OnlyOffice\Model\FileTemplate;
use ILIAS\Plugin\OnlyOffice\Upload\UploadHandler;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ilLanguage;
use ilOnlyOfficePlugin;

class FileSettingProperty implements Property
{
    private readonly ilOnlyOfficePlugin $plugin;
    private readonly Filesystem $tmp_filesystem;

    /**
     * @param FileTemplate[] $templates
     */
    public function __construct(
        private readonly array $templates = [],
        private readonly FileMode $file_mode = FileMode::UPLOAD,
        private readonly ?UploadResult $upload_result = null,
        private readonly ?FileCreationType $file_creation_type = null,
        private readonly ?int $file_template_id = null,
    ) {
        global $DIC;
        $this->tmp_filesystem = $DIC->filesystem()->temp();
        $this->plugin = ilOnlyOfficePlugin::getInstance();
    }

    public function toForm(
        ilLanguage $language,
        FieldFactory $field_factory,
        Refinery $refinery
    ): FormInput {
        $trafo = $refinery->custom()->transformation(
            function ($vs): self {
                $file_mode = FileMode::from($vs[0]);

                $upload_result = null;
                $file_creation_type = null;
                $file_template_id = null;
                switch ($file_mode) {
                    case FileMode::UPLOAD:
                        $filesystem_path = end($vs[1][ObjectSettingsForm::POST_VAR_FILE]);
                        $stream = $this->tmp_filesystem->readStream($filesystem_path);
                        $file_path = $stream->getMetadata("uri");

                        $upload_result = new UploadResult(
                            basename($file_path),
                            $stream->getSize(),
                            mime_content_type($file_path),
                            new ImmutableMapWrapper(new EntryLockingStringMap()),
                            new ProcessingStatus(ProcessingStatus::OK, "Upload Ok"),
                            $file_path
                        );
                        $stream->close();

                        break;
                    case FileMode::CREATE:
                        $file_creation_type = FileCreationType::from($vs[1][ObjectSettingsForm::POST_VAR_FILE_CREATION_SETTING]);
                        break;

                    case FileMode::TEMPLATE:
                        $file_template_id = $vs[1][ObjectSettingsForm::POST_VAR_FILE_TEMPLATE_SETTING]
                            ? (int) $vs[1][ObjectSettingsForm::POST_VAR_FILE_TEMPLATE_SETTING]
                            : null;
                        break;
                }

                return new FileSettingProperty(
                    $this->templates,
                    $file_mode,
                    $upload_result,
                    $file_creation_type,
                    $file_template_id
                );
            }
        );

        $template_radio_option = $field_factory->radio("");
        foreach ($this->templates as $template) {
            $type_translation = sprintf("form_template_%s", $template->getType()->value);
            $template_radio_option = $template_radio_option->withOption(
                (string) $template->getId(),
                sprintf("%s %s", $template->getTitle(), $this->plugin->txt($type_translation)),
                !empty($template->getDescription()) ? $template->getDescription() : null
            );
        }

        $template_option = $field_factory->group([
            ObjectSettingsForm::POST_VAR_FILE_TEMPLATE_SETTING => $template_radio_option
        ], $this->plugin->txt('form_input_template'))->withRequired(true);

        $file_options = [
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
                        ->withOption(FileCreationType::TEXT->value, $this->plugin->txt('form_input_create_file_text'))
                        ->withOption(FileCreationType::TABLE->value, $this->plugin->txt('form_input_create_file_table'))
                        ->withOption(FileCreationType::PRESENTATION->value, $this->plugin->txt('form_input_create_file_presentation'))
                ],
                $this->plugin->txt('form_input_create_file')
            ),
        ];
        if ($this->templates !== []) {
            $file_options[ObjectSettingsForm::OPTION_SETTING_TEMPLATE] = $template_option;
        }

        return $field_factory->switchableGroup(
            $file_options,
            $this->plugin->txt('form_input_file'),
            $this->templates === [] ? $this->plugin->txt('form_input_template_no_templates') : null
        )
            ->withRequired(true)
            ->withAdditionalTransformation($trafo)
            ->withValue($this->getFileMode()->value);
    }

    public function getFileMode(): FileMode
    {
        return $this->file_mode;
    }

    public function getUploadResult(): ?UploadResult
    {
        return $this->upload_result;
    }

    public function getFileCreationType(): ?FileCreationType
    {
        return $this->file_creation_type;
    }

    public function getFileTemplateId(): ?int
    {
        return $this->file_template_id;
    }
}
