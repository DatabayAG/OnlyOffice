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

require_once __DIR__ . "/../vendor/autoload.php";

use ILIAS\DI\Container;
use ILIAS\Plugin\OnlyOffice\Repository;

class ilObjOnlyOfficeAccess extends ilObjectPluginAccess
{
    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    protected static ?ilObjOnlyOfficeAccess $instance = null;

    private Container $dic;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        parent::__construct();
        global $DIC;
        $this->dic = $DIC;
    }

    public function _checkAccess(string $cmd, string $permission, ?int $ref_id = null, ?int $obj_id = null, ?int $user_id = null): bool
    {
        if ($ref_id === null) {
            $ref_id = (int) filter_input(INPUT_GET, "ref_id");
        }

        if ($obj_id === null) {
            $obj_id = ilObjOnlyOffice::_lookupObjectId($ref_id);
        }

        if ($user_id === null) {
            $user_id = $this->dic->user()->getId();
        }

        return match ($permission) {
            "visible", "read" => ($this->dic->access()->checkAccessOfUser($user_id, $permission, "", $ref_id) && !self::_isOffline($obj_id))
                || $this->dic->access()->checkAccessOfUser($user_id, "write", "", $ref_id),
            "delete" => $this->dic->access()->checkAccessOfUser($user_id, "delete", "", $ref_id)
                || $this->dic->access()->checkAccessOfUser($user_id, "write", "", $ref_id),
            "editFile" => $this->dic->access()->checkAccessOfUser($user_id, "rep_robj_xono_perm_editFile", "", $ref_id),
            default => $this->dic->access()->checkAccessOfUser($user_id, $permission, "", $ref_id),
        };
    }

    protected static function checkAccess(string $cmd, string $a_permission, ?int $a_ref_id = null, ?int $a_obj_id = null, ?int $a_user_id = null): bool
    {
        return self::getInstance()->_checkAccess($cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id);
    }

    public static function redirectNonAccess($class, string $cmd = ""): void
    {
        global $DIC;
        /** @var $component_factory ilComponentFactory */
        $component_factory = $DIC['component.factory'];
        /** @var $plugin ilOnlyOfficePlugin */
        $pl = $component_factory->getPlugin(ilOnlyOfficePlugin::PLUGIN_ID);
        $tpl = $DIC->ui()->mainTemplate();
        $tpl->setOnScreenMessage('failure', $pl->txt("object_permission_denied"), true);

        if (is_object($class)) {
            $DIC->ctrl()->clearParameters($class);
            $DIC->ctrl()->redirect($class, $cmd);
        } else {
            $DIC->ctrl()->clearParametersByClass($class);
            $DIC->ctrl()->redirectByClass($class, $cmd);
        }
    }

    public static function _isOffline(?int $obj_id): bool
    {
        $object_settings = Repository::getInstance()->objectSettings()->getObjectSettingsById(intval($obj_id));

        if ($object_settings !== null) {
            return (!$object_settings->isOnline());
        }

        return true;
    }

    public static function hasVisibleAccess(?int $ref_id = null): bool
    {
        return self::checkAccess("visible", "visible", $ref_id);
    }

    public static function hasReadAccess(?int $ref_id = null): bool
    {
        return self::checkAccess("read", "read", $ref_id);
    }

    public static function hasWriteAccess(?int $ref_id = null): bool
    {
        return self::checkAccess("write", "write", $ref_id);
    }

    public static function hasEditFileAccess(?int $ref_id = null): bool
    {
        return self::checkAccess("editFile", "editFile", $ref_id);
    }

    public static function hasDeleteAccess(?int $ref_id = null): bool
    {
        return self::checkAccess("delete", "delete", $ref_id);
    }

    public static function hasEditPermissionAccess(?int $ref_id = null): bool
    {
        return self::checkAccess("edit_permission", "edit_permission", $ref_id);
    }
}
