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
[$inst, $editMode] = $validator->existingIdPInt($_GET['inst_id'], $_SESSION['user']);

if ($editMode == 'fullaccess'|| $editMode === 'readonly') {
    $deployment = $validator->existingDeploymentManaged($_GET['deployment_id'], $inst);
    $deployment_id = $_GET['deployment_id'];
    $inst_id = $_GET['inst_id'];
    $backlog = 1;
    if (isset($_GET['backlog'])) {
        $backlog = $_GET['backlog'];
    } 
    $deployment->getRADIUSLogs(0, $backlog);
}

