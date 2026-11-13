<#1>
<?php
/** @var ilDBInterface $ilDB */
//Kept empty for compatibility
?>
<#2>
<?php
//Kept empty for compatibility
?>
<#3>
<?php
//Kept empty for compatibility
?>
<#4>
<?php
//Kept empty for compatibility
?>
<#5>
<#6>
<#7>
<?php
require_once(ILIAS_ABSOLUTE_PATH . "/components/ILIAS/Migration/DBUpdate_3560/classes/class.ilDBUpdateNewObjectType.php");
$xono_type_id = ilDBUpdateNewObjectType::addNewType(ilOnlyOfficePlugin::ID, 'Plugin OnlyOffice');

//Adding a new Permission rep_robj_xono_editFile ("editFile")
$offering_admin = ilDBUpdateNewObjectType::addCustomRBACOperation( //$a_id, $a_title, $a_class, $a_pos
    'rep_robj_xono_perm_editFile', 'editFile', 'object', 2010);
if ($offering_admin) {
    ilDBUpdateNewObjectType::addRBACOperation($xono_type_id, $offering_admin);
}
?>
<#8>
<?php
//Kept empty for compatibility
?>
<#9>
<?php
//Kept empty for compatibility
?>
<#10>
<?php
//Kept empty for compatibility
?>
<#11>
<?php
//Kept empty for compatibility
?>
<#12>
<#13>
<?php
//Kept empty for compatibility
?>
<#14>
<?php
//Kept empty for compatibility
?>
<#15>
<?php
//Kept empty for compatibility
?>
<#16>
<?php
//Kept empty for compatibility
?>
<#17>
<?php
//Kept empty for compatibility
?>
<#18>
<?php
//Kept empty for compatibility
?>
