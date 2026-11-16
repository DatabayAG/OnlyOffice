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

use DateTimeImmutable;
use ILIAS\ILIASObject\Properties\Property;
use ILIAS\Plugin\OnlyOffice\Form\ObjectSettingsForm;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ilLanguage;
use ilOnlyOfficePlugin;

class AllowEditProperty implements Property
{
    private readonly ilOnlyOfficePlugin $plugin;

    public function __construct(
        private readonly bool $allow_edit = true,
        private readonly bool $limited_period = false,
        private readonly ?DateTimeImmutable $start_time = null,
        private readonly ?DateTimeImmutable $end_time = null
    ) {
        $this->plugin = ilOnlyOfficePlugin::getInstance();
    }

    public function toForm(
        ilLanguage $language,
        FieldFactory $field_factory,
        Refinery $refinery
    ): FormInput {
        $trafo = $refinery->custom()->transformation(
            function ($vs): Property {
                $edit_limited = $vs[ObjectSettingsForm::POST_VAR_EDIT][ObjectSettingsForm::POST_VAR_EDIT_LIMITED] ?? [];
                return new AllowEditProperty(
                    isset($vs[ObjectSettingsForm::POST_VAR_EDIT]),
                    $edit_limited !== [],
                    $edit_limited[ObjectSettingsForm::POST_VAR_EDIT_LIMITED_START] ?? null,
                    $edit_limited[ObjectSettingsForm::POST_VAR_EDIT_LIMITED_END] ?? null
                );
            }
        );

        return $field_factory->optionalGroup([
            ObjectSettingsForm::POST_VAR_EDIT => $field_factory->group([
                ObjectSettingsForm::POST_VAR_EDIT_LIMITED => $field_factory->optionalGroup([
                    ObjectSettingsForm::POST_VAR_EDIT_LIMITED_START => $field_factory->dateTime(
                        $this->plugin->txt('settings_allow_edit_limited_start')
                    )->withUseTime(true)->withRequired(true),
                    ObjectSettingsForm::POST_VAR_EDIT_LIMITED_END => $field_factory->dateTime(
                        $this->plugin->txt('settings_allow_edit_limited_end')
                    )->withUseTime(true)->withRequired(true)
                ], $this->plugin->txt('settings_allow_edit_limited'))
            ], $this->plugin->txt('settings_allow_edit_limited'))
        ], $this->plugin->txt('settings_allow_edit'), $this->plugin->txt('settings_allow_edit_info'))
            ->withAdditionalTransformation($trafo)->withValue(
                $this->isAllowEdit()
                ? [
                    ObjectSettingsForm::POST_VAR_EDIT => [
                        ObjectSettingsForm::POST_VAR_EDIT_LIMITED => $this->isLimitedPeriod()
                            ? [
                                ObjectSettingsForm::POST_VAR_EDIT_LIMITED_START => $this->getStartTime(),
                                ObjectSettingsForm::POST_VAR_EDIT_LIMITED_END => $this->getEndTime()
                            ]
                            : null
                    ]
                ]
                : null
            );
    }

    public function isAllowEdit(): bool
    {
        return $this->allow_edit;
    }

    public function isLimitedPeriod(): bool
    {
        return $this->limited_period;
    }

    public function getStartTime(): ?DateTimeImmutable
    {
        return $this->start_time;
    }

    public function getEndTime(): ?DateTimeImmutable
    {
        return $this->end_time;
    }
}
