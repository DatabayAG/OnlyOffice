<?php
// Try to determine ILIAS-root
$directory = strstr($_SERVER['SCRIPT_FILENAME'], 'Customizing', true);
if (is_file('path.txt')) {
    $directory = trim(file_get_contents('path.txt'));
}

chdir($directory);
//echo get_class($DIC->database());
// use database

initializeILIAS();
global $DIC;
//$DIC->logger()->root()->info("Ilias initialized");

if (($body_stream = file_get_contents("php://input")) === false) {
    echo "Bad Request";
}

//$DIC->logger()->root()->info($body_stream);
$encrypted = json_decode($body_stream, true);
require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/OnlyOffice/src/CryptoService/JwtService.php';
require_once 'Customizing/global/plugins/Services/Repository/RepositoryObject/OnlyOffice/src/StorageService/InfoService.php';
$secret = \srag\Plugins\OnlyOffice\InfoService\InfoService::getSecret();
$decrypted = \srag\Plugins\OnlyOffice\CryptoService\JwtService::jwtDecode($encrypted['token'],
    $secret);
//$DIC->logger()->root()->info($decrypted);
$data = json_decode($decrypted, true);

if ($data["status"] == 2 && !$data["notmodified"] || $data["status"] == 6) {
    $DIC->logger()->root()->info("Save File");
    $uuid = $_GET['uuid'];
    $file_id = $_GET['file_id'];
    $file_ext = $_GET['ext'];

    try {
        $callback_handler = new xonoCallbackHandler($DIC, $uuid, $file_id, $data);
        $callback_handler->handleCallback();

    } catch (Exception $e) {
        echo $e->getMessage();
    }

}
echo "{\"error\":0}";
exit;

//-------------------------------------------------------------------

function initializeILIAS()
{
    try {
        require_once ("Services/Context/classes/class.ilContext.php");
        ilContext::init(ilContext::CONTEXT_SOAP_NO_AUTH);
        require_once("Services/Init/classes/class.ilInitialisation.php");
        ilInitialisation::initILIAS();
    }
    catch (Exception $exception) {
        echo "Bad Request";
        exit;
    }
}