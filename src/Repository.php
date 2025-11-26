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

namespace ILIAS\Plugin\OnlyOffice;

use ILIAS\Plugin\OnlyOffice\ObjectSettings\Repository as ObjectSettingsRepository;
use ilOnlyOfficePlugin;

final class Repository
{
    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    protected static ?Repository $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {

    }

    public function dropTables(): void
    {
        $this->objectSettings()->dropTables();
    }

    public function installTables(): void
    {
        $this->objectSettings()->installTables();
    }

    public function objectSettings(): ObjectSettingsRepository
    {
        return ObjectSettingsRepository::getInstance();
    }
}
