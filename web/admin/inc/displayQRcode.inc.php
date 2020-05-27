<?php
/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */
?>
<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$languageInstance = new \core\common\Language();
$uiElements = new web\lib\admin\UIElements();

$auth->authenticate();
$languageInstance->setTextDomain("web_admin");
$validator = new \web\lib\common\InputValidation();
$user = new \core\User($_SESSION['user']);
$mgmt = new \core\UserManagement();

if (!isset($_POST['token'])) {
    exit;
}
$invitationObject = new core\SilverbulletInvitation($validator->token(filter_input(INPUT_POST, 'token')));
header("Content-Type:text/html;charset=utf-8");
?>
<h1 style='text-align:center;'><?php echo _("Invitation Token QR Code");?></h1>
<img alt='<?php echo _("Invitation Token QR Code");?>' style='float:none' src='data:image/png;base64,<?php 
$size = 10;
$qrCode = new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
                    'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                    'eccLevel' => \chillerlan\QRCode\QRCode::ECC_H,
                    'scale' => $size,
                    'imageBase64' => FALSE,
        ]));
$rawQr = $qrCode->render($invitationObject->link());
if (empty($rawQr)) {
    throw new Exception("Something went seriously wrong during QR code generation!");
}
echo base64_encode($uiElements->pngInjectConsortiumLogo($rawQr, $size));?>'/>
<p>(<a href='<?php echo $invitationObject->link();?>'><?php echo $invitationObject->link();?>)</a></p>
