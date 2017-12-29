<?php
/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */
?>
<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/core/phpqrcode.php");

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
$cleanToken = $validator->token(filter_input(INPUT_POST, 'token'));
$link = \core\ProfileSilverbullet::generateTokenLink($cleanToken);
header("Content-Type:text/html;charset=utf-8");
?>
<h1 style='text-align:center;'><?php echo _("Invitation Token QR Code");?></h1>
<img style='float:none' src='data:image/png;base64,<?php echo base64_encode($uiElements->pngInjectConsortiumLogo(\QRcode::png($link, FALSE, QR_ECLEVEL_Q, 11), 11));?>'/>
<p>(<a href='<?php echo $link;?>'><?php echo $link;?>)</a></p>
