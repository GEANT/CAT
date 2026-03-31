<?php

require_once dirname(dirname(dirname(__FILE__))) . "/config/_config.php";

$auth = new \web\lib\admin\Authentication();
$validator = new \web\lib\common\InputValidation();
$optionParser = new \web\lib\admin\OptionParser();
$uiElements = new \web\lib\admin\UIElements();

$auth->authenticate();

$my_inst = $validator->existingIdP($_POST['idp_id'], $_SESSION['user']);
if ($my_inst == false) {
    return;
}
$my_profile = $validator->existingProfile($_POST['profile_id'], $my_inst->identifier);

$level =  filter_input(INPUT_POST, 'level', FILTER_VALIDATE_INT);
$my_profile->setTestStatusInfo($level);