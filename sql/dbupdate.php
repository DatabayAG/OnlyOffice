<#1>
<?php
/** @var ilDBInterface $ilDB */
//Kept empty for compatibility
?>
<#2>
<?php
\ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileAR::updateDB();
?>
<#3>
<?php
\ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileChangeAR::updateDB();
?>
<#4>
<?php
\ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileAR::updateDB();
\ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileAR::updateDB();
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
\ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileAR::updateDB();
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
\ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileChangeAR::updateDB();
global $DIC;
$DIC->database()->query("CREATE TABLE IF NOT EXISTS xono_file_change_seq (sequence INT PRIMARY KEY AUTO_INCREMENT);");
$DIC->database()->query("INSERT INTO xono_file_change_seq VALUES (1);");
?>
<#14>
<?php
//Kept empty for compatibility
?>
<#15>
<?php
global $DIC;
$file_change_table = \ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileChangeAR::TABLE_NAME;

$DIC->database()->modifyTableColumn($file_change_table, 'changes_object_string',
    array("type" => "clob"));
?>
<#16>
<?php
//Kept empty for compatibility
?>
<#17>
<?php
\ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileAR::updateDB();
?>
<#18>
<?php
global $DIC;
$DIC->database()->modifyTableColumn(\ILIAS\Plugin\OnlyOffice\StorageService\Infrastructure\File\FileAR::TABLE_NAME, 'mime_type', array('length' => 256));
?>
