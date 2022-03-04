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
$inst = $validator->existingIdP($_GET['inst_id'], $_SESSION['user']);
$deployment = $validator->existingDeploymentManaged($_GET['deployment_id'], $inst);
$backlog = $validator->string($_POST['stats']);
$backlogTime = 0;
switch ($backlog) {
    case "HOUR":
        $backlogTime = 60 * 60;
        break;
    case "MONTH":
        $backlogTime = 60 * 60 * 24 * 30;
        break;
    case "FULL":
        $backlogTime = 60 * 60 * 24 * 30 * 6;
        break;
    default:
        throw new Exception("Unexpected backlog time!");
}

?>

<h1><?php $tablecaption = _("Deployment Usage Records"); echo $tablecaption; ?></h1>
<table class='authrecord'>
    <caption><?php echo $tablecaption;?></caption>
    <tr>
        <th scope="col"><strong><?php echo _("Timestamp (UTC)");?></strong></th>
        <th scope="col"><strong><?php echo _("Realm");?></strong></th>
        <th scope="col"><strong><?php echo _("MAC Address");?></strong></th>
        <th scope="col"><strong><?php echo _("Chargeable-User-Identity");?></strong></th>
        <th scope="col"><strong><?php echo _("Result");?></strong></th>
    </tr>
    <?php
    $userAuthData = $deployment->retrieveStatistics($backlogTime);
    foreach ($userAuthData as $oneRecord) {
        echo "<tr class='".($oneRecord['result'] == "OK" ? "auth-success" : "auth-fail" )."'>"
                . "<td>".$oneRecord['activity_time']."</td>"
                // $oneRecord['CN'] is a simple string, not an array, so disable Scrutinizer type check here
                . "<td>".$oneRecord['realm']."</td>"
                . "<td>".$oneRecord['mac']."</td>"
                . "<td>".$oneRecord['cui']."</td>"
                . "<td>".($oneRecord['result'] == "OK" ? _("Success") : _("Failure"))."</td>"
                . "</tr>";
    }
    ?>
</table>