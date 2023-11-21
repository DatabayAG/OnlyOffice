<#1>
<?php
\srag\Plugins\OnlyOffice\Repository::getInstance()->installTables();
?>
<#2>
<?php
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileAR::updateDB();
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileVersionAR::updateDB();
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileVersionAR::updateDB();
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileVersionAR::updateDB();
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileVersionAR::updateDB();
?>
<#3>
<?php
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileChangeAR::updateDB();
?>
<#4>
<?php
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileAR::updateDB();
\srag\Plugins\OnlyOffice\ObjectSettings\ObjectSettings::updateDB();
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileAR::updateDB();
?>
<#5>
<#6>
<#7>
<?php
require_once("./Services/Migration/DBUpdate_3560/classes/class.ilDBUpdateNewObjectType.php");
$xono_type_id = ilDBUpdateNewObjectType::addNewType(ilOnlyOfficePlugin::PLUGIN_ID, 'Plugin OnlyOffice');

//Adding a new Permission rep_robj_xono_editFile ("editFile")
$offering_admin = ilDBUpdateNewObjectType::addCustomRBACOperation( //$a_id, $a_title, $a_class, $a_pos
    'rep_robj_xono_perm_editFile', 'editFile', 'object', 2010);
if ($offering_admin) {
    ilDBUpdateNewObjectType::addRBACOperation($xono_type_id, $offering_admin);
}
?>
<#8>
<?php
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileAR::updateDB();
?>
<#9>
<?php
\srag\Plugins\OnlyOffice\ObjectSettings\ObjectSettings::updateDB();
?>
<#10>
<?php
\srag\Plugins\OnlyOffice\ObjectSettings\ObjectSettings::updateDB();
?>
<#11>
<?php
\srag\Plugins\OnlyOffice\ObjectSettings\ObjectSettings::updateDB();
?>
<#12>
<#13>
<?php
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileChangeAR::updateDB();
global $DIC;
$DIC->database()->query("CREATE TABLE IF NOT EXISTS xono_file_change_seq (sequence INT PRIMARY KEY AUTO_INCREMENT);");
$DIC->database()->query("INSERT INTO xono_file_change_seq VALUES (1);");
?>
<#14>
<?php
global $DIC;
$file_versions = \srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileVersionAR::get();
$table_to_update = \srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileVersionAR::TABLE_NAME;
foreach ($file_versions as $file_version) {
    $file_version->getCreatedAt()->increment(ilDateTime::HOUR, -2);
    $file_uuid = $file_version->getFileUuid()->asString();
    $new_date_time = $file_version->getCreatedAt()->get(IL_CAL_DATETIME, 'd.m.Y H:i', ilTimeZone::UTC);
    $DIC->database()->manipulate(sprintf("UPDATE %s SET created_at = '%s' WHERE file_uuid = '%s'", $table_to_update, $new_date_time, $file_uuid));
}
?>
<#15>
<?php
global $DIC;
$file_change_table = \srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileChangeAR::TABLE_NAME;

$DIC->database()->modifyTableColumn($file_change_table, 'changes_object_string',
    array("type" => "clob"));
?>
<#16>
<?php
\srag\Plugins\OnlyOffice\ObjectSettings\ObjectSettings::updateDB();
?>
<#17>
<?php
\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileAR::updateDB();
?>
<#18>
<?php
global $DIC;
$DIC->database()->modifyTableColumn(\srag\Plugins\OnlyOffice\StorageService\Infrastructure\File\FileAR::TABLE_NAME, 'mime_type', array('length' => 256));
?>