<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$auth->authenticate();
$languageInstance = new \core\common\Language();
$languageInstance->setTextDomain("web_admin");
$validator = new web\lib\common\InputValidation();
$inst = $validator->IdP($_GET['inst_id'], $_SESSION['user']);
$profile = $validator->Profile($_GET['profile_id'], $inst->identifier);
if (!$profile instanceof core\ProfileSilverbullet) {
    throw new Exception("This is not a Silverbullet profile!");
}
$userInt = $validator->integer($_GET['user_id']);
if ($userInt === FALSE) {
    throw new Exception("This is not an integer user identifier!");
}

?>

<h1><?php echo _("User Authentication Records");?></h1>
<p><?php echo _("Note that:");?></p>
<ul>
    <li><?php echo _("Authentication records are deleted after six months retention time");?></li>
    <li><?php echo _("Operator Domain is based on the RADIUS attribute 'Operator-Name' and not sent by all hotspots");?></li>
    <li><?php echo _("Different MAC addresses per credential may be due to MAC Address randomisation in recent operating systems");?></li>
</ul>
<table class='authrecord'>
    <tr>
        <td><strong><?php echo _("Timestamp");?></strong></td>
        <td><strong><?php echo _("Credential");?></strong></td>
        <td><strong><?php echo _("MAC Address");?></strong></td>
        <td><strong><?php echo _("Result");?></strong></td>
        <td><strong><?php echo _("Operator Domain");?></strong></td>
    </tr>
    <?php
    $userAuthData = $profile->getUserAuthRecords($userInt);
    foreach ($userAuthData as $oneRecord) {
        echo "<tr class='".($oneRecord['RESULT'] == "Access-Accept" ? "auth-success" : "auth-fail" )."'>"
                . "<td>".$oneRecord['TIMESTAMP']."</td>"
                . "<td>".substr_replace($oneRecord['CN'], "@…", strpos($oneRecord['CN'],"@"))."</td>"
                . "<td>".$oneRecord['MAC']."</td>"
                . "<td>".($oneRecord['RESULT'] == "Access-Accept" ? _("Success") : _("Failure"))."</td>"
                . "<td>".substr($oneRecord['OPERATOR'] ?? "1(unknown)",1)."</td>"
                . "</tr>";
    }
    ?>
</table>