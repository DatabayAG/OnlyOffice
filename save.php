<?php
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
//$DIC->logger()->root()->info("Ilias initialized");

if (($body_stream = file_get_contents("php://input")) === false) {
    echo "Bad Request";
}

$encrypted = json_decode($body_stream, true);

$plugin = ilOnlyOfficePlugin::getInstance();

$secret = $plugin->settings->get(PluginConfigForm::KEY_ONLYOFFICE_SECRET, "");
$decrypted = \ILIAS\Plugin\OnlyOffice\CryptoService\JwtService::jwtDecode($encrypted['token'],
    $secret);
//$DIC->logger()->root()->info($decrypted);
$data = json_decode($decrypted, true);

if ($data["status"] == 2) {
    $DIC->logger()->root()->info("Save File");
    $httpWrapper = $DIC->http()->wrapper();
    $refinery = $DIC->refinery();

    $uuid = $httpWrapper->query()->retrieve("uuid", $refinery->kindlyTo()->string());
    $file_id = $httpWrapper->query()->retrieve("file_id", $refinery->kindlyTo()->int());
    $file_ext = $httpWrapper->query()->retrieve("ext", $refinery->kindlyTo()->string());
    $DIC->logger()->root()->dump($data);

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
        ilContext::init(ilContext::CONTEXT_SOAP_NO_AUTH);
        ilInitialisation::initILIAS();
    }
    catch (Exception $exception) {
        echo "Bad Request";
        exit;
    }
}
