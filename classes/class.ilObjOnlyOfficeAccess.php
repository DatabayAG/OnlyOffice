<?php

require_once __DIR__ . "/../vendor/autoload.php";
use srag\Plugins\OnlyOffice\Utils\OnlyOfficeTrait;
use srag\DIC\OnlyOffice\DICTrait;

class ilObjOnlyOfficeAccess extends ilObjectPluginAccess
{
    use DICTrait;
    use OnlyOfficeTrait;
    public const PLUGIN_CLASS_NAME = ilOnlyOfficePlugin::class;
    protected static ?ilObjOnlyOfficeAccess $instance = null;

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
    }

    public function _checkAccess(string $a_cmd, string $a_permission, ?int $a_ref_id = null, ?int $a_obj_id = null, ?int $a_user_id = null): bool
    {
        if ($a_ref_id === null) {
            $a_ref_id = filter_input(INPUT_GET, "ref_id");
        }

        if ($a_obj_id === null) {
            $a_obj_id = ilObjOnlyOffice::_lookupObjectId($a_ref_id);
        }

        if ($a_user_id == null) {
            $a_user_id = self::dic()->user()->getId();
        }

        switch ($a_permission) {
            case "visible":
            case "read":
                return boolval((self::dic()->access()->checkAccessOfUser($a_user_id, $a_permission, "", $a_ref_id) && !self::_isOffline($a_obj_id))
                    || self::dic()->access()->checkAccessOfUser($a_user_id, "write", "", $a_ref_id));

            case "delete":
                return boolval(self::dic()->access()->checkAccessOfUser($a_user_id, "delete", "", $a_ref_id)
                    || self::dic()->access()->checkAccessOfUser($a_user_id, "write", "", $a_ref_id));
            case "editFile":
                return boolval(self::dic()->access()->checkAccessOfUser($a_user_id, "rep_robj_xono_perm_editFile", "", $a_ref_id));
            case "write":
            case "edit_permission":
            default:
                return boolval(self::dic()->access()->checkAccessOfUser($a_user_id, $a_permission, "", $a_ref_id));
        }
    }

    protected static function checkAccess(string $a_cmd, string $a_permission, ?int $a_ref_id = null, ?int $a_obj_id = null, ?int $a_user_id = null): bool
    {
        return self::getInstance()->_checkAccess($a_cmd, $a_permission, $a_ref_id, $a_obj_id, $a_user_id);
    }

    public static function redirectNonAccess($class, string $cmd = ""): void
    {
        global $DIC;
        /** @var $component_factory ilComponentFactory */
        $component_factory = $DIC['component.factory'];
        /** @var $plugin ilOnlyOfficePlugin */
        $pl = $component_factory->getPlugin(ilOnlyOfficePlugin::PLUGIN_ID);
        $tpl = $DIC["tpl"];
        $tpl->setOnScreenMessage('failure', $pl->txt("object_permission_denied"), true);

        if (is_object($class)) {
            self::dic()->ctrl()->clearParameters($class);
            self::dic()->ctrl()->redirect($class, $cmd);
        } else {
            self::dic()->ctrl()->clearParametersByClass($class);
            self::dic()->ctrl()->redirectByClass($class, $cmd);
        }
    }

    public static function _isOffline(?int $a_obj_id): bool
    {
        $object_settings = self::onlyOffice()->objectSettings()->getObjectSettingsById(intval($a_obj_id));

        if ($object_settings !== null) {
            return (!$object_settings->isOnline());
        } else {
            return true;
        }
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
