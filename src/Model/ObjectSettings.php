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

namespace ILIAS\Plugin\OnlyOffice\Model;

use ilDateTime;
use ILIAS\Plugin\OnlyOffice\Enum\OpenSetting;

class ObjectSettings
{
    public function __construct(
        private readonly int $obj_id,
        private string $title,
        private string $description,
        private OpenSetting $open_setting = OpenSetting::ILIAS,
        private bool $online = false,
        private bool $allow_edit = false,
        private bool $limited_period = false,
        private ?ilDateTime $start_time = null,
        private ?ilDateTime $end_time = null,
    ) {
    }

    public function getObjId(): int
    {
        return $this->obj_id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }


    public function isOnline(): bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): self
    {
        $this->online = $online;
        return $this;
    }

    public function isAllowEdit(): bool
    {
        return $this->allow_edit;
    }

    public function setAllowEdit(bool $allow_edit): self
    {
        $this->allow_edit = $allow_edit;
        return $this;
    }

    public function isLimitedPeriod(): bool
    {
        return $this->limited_period;
    }

    public function setLimitedPeriod(bool $limited_period): self
    {
        $this->limited_period = $limited_period;
        return $this;
    }

    public function getOpenSetting(): OpenSetting
    {
        return $this->open_setting;
    }

    public function setOpenSetting(OpenSetting $open_setting): self
    {
        $this->open_setting = $open_setting;
        return $this;
    }

    public function getStartTime(): ?ilDateTime
    {
        return $this->start_time;
    }

    public function setStartTime(?ilDateTime $start_time): self
    {
        $this->start_time = $start_time;
        return $this;
    }

    public function getEndTime(): ?ilDateTime
    {
        return $this->end_time;
    }

    public function setEndTime(?ilDateTime $end_time): self
    {
        $this->end_time = $end_time;
        return $this;
    }
}
