<?php

use ILIAS\Plugin\OnlyOffice\CryptoService\JwtService;
use ILIAS\Plugin\OnlyOffice\Form\PluginConfigForm;

chdir(__DIR__);
$ilias_main_directory = './';
while (!file_exists($ilias_main_directory . 'ilias.ini.php')) {
    $ilias_main_directory .= '../';
}
chdir($ilias_main_directory);

require_once './vendor/composer/vendor/autoload.php';

initializeILIAS();
global $DIC;

if (($body_stream = file_get_contents("php://input")) === false) {
    echo "Bad Request";
}

$encrypted = json_decode($body_stream, true);

$plugin = ilOnlyOfficePlugin::getInstance();

$secret = $plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_SECRET, "");
$decrypted = JwtService::jwtDecode($encrypted['token'], $secret);

$data = json_decode($decrypted, true);

if ($data["status"] === 2) {
    $DIC->logger()->root()->info("Save File");
    $httpWrapper = $DIC->http()->wrapper();
    $refinery = $DIC->refinery();

    $uuid = $httpWrapper->query()->retrieve("uuid", $refinery->kindlyTo()->string());
    $file_id = $httpWrapper->query()->retrieve("file_id", $refinery->kindlyTo()->int());
    $file_ext = $httpWrapper->query()->retrieve("ext", $refinery->kindlyTo()->string());

    try {
        $callback_handler = new xonoCallbackHandler($DIC, $uuid, $file_id, $data);
        $callback_handler->handleCallback();

    } catch (Exception $e) {
        echo $e->getMessage();
    }

}
echo json_encode(["error" => 0], JSON_THROW_ON_ERROR);
exit;

//-------------------------------------------------------------------

function initializeILIAS(): void
{
    try {
        ilContext::init(ilContext::CONTEXT_SOAP_NO_AUTH);
        ilInitialisation::initILIAS();
    }
    catch (Exception $exception) {
        echo "Bad Request";
        exit;
    }
}
