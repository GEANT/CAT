<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */
?>
<?php
/*
 * Class autoloader invocation, should be included prior to any other code at the entry points to the application
 */
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("inc/common.inc.php");
require_once("inc/auth.inc.php");

use web\lib\admin\http\SilverbulletController;
use web\lib\admin\http\TermsOfUseCommand;
use web\lib\admin\view\DefaultPage;
use web\lib\admin\view\FileUploadForm;
use web\lib\admin\view\InfoBlockTable;
use web\lib\admin\view\InstitutionPageBuilder;
use web\lib\admin\view\PageBuilder;
use web\lib\admin\view\PageElementInterface;
use web\lib\admin\view\TermsOfUseBox;
use web\lib\admin\view\TitledBlockDecorator;
use web\lib\admin\view\UserCredentialsForm;

authenticate();

$page = new DefaultPage(_('Managing institution users'));
// Load global scripts
$page->appendScript('js/option_expand.js');
$page->appendScript('../external/jquery/jquery.js');
$page->appendScript('../external/jquery/jquery-migrate-1.2.1.js');
// Load Silverbullet scripts
$page->appendScript('js/silverbullet.js');
$page->appendScript('js/edit_silverbullet.js');
// Load Silverbullet CSS
$page->appendCss('css/silverbullet.css');
$builder = new InstitutionPageBuilder($page, PageBuilder::ADMIN_IDP_USERS);
if($builder->isReady()){
    // this page may have been called for the first time, when the profile does not
    // actually exist in the DB yet. If so, we will need to create it first.
    if (!isset($_REQUEST['profile_id'])) {
        // someone might want to trick himself into this page by sending an inst_id but
        // not having permission for silverbullet. Sanity check that the fed in question
        // does allow SB and that the IdP doesn't have any non-SB profiles
        $inst = $builder->getInstitution();
        if ($inst->profileCount() > 0) {
            throw new Exception("We were told to create a new SB profile, but the inst in question already has at least one profile!");
        }
        $fed = new \core\Federation($inst->federation);
        $allowSb = $fed->getAttributes("fed:silverbullet");
        if (count($allowSb) == 0) {
            throw new Exception("We were told to create a new SB profile, but this federation does not allow SB at all!");
        }
        // okay, new SB profiles are allowed. Create one.
        $newProfile = $inst->newProfile("SILVERBULLET");
        // and modify the REQUEST_URI to add the new profile ID
        $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI']."&profile_id=".$newProfile->identifier;
        $_GET['profile_id'] = $newProfile->identifier;
    }
    
    $controller = new SilverbulletController($builder);
    $controller->parseRequest();
    
    $users = $controller->createUsers();
    $stats = $controller->getUserStats();
    
    //Info block data preparation
    $infoBlock = new InfoBlockTable( _('Current institution users'));
    $infoBlock->addRow(array('The assigned realm', $builder->getRealmName()));
    $infoBlock->addRow(array('The total number of active users which are allowed for this profile', $stats[SilverbulletController::STATS_TOTAL]));
    $infoBlock->addRow(array('The current number of configured active users', $stats[SilverbulletController::STATS_ACTIVE]));
    $infoBlock->addRow(array('The current number of configured inactive users', $stats[SilverbulletController::STATS_PASSIVE]));
    $builder->addContentElement($infoBlock);

    //User import form preparation
    $importForm = new FileUploadForm($controller, _('Comma separated values should be provided in CSV file: username, expiration date "yyyy-mm-dd", number of tokens (optional):'));
    $importBlock = new TitledBlockDecorator($importForm, _('Import users from CSV file'), PageElementInterface::INFOBLOCK_CLASS);
    $builder->addContentElement($importBlock);

    //Edit form data preparation
    $editBlock = new UserCredentialsForm(_('Manage institution users'), $controller, count($users) > 0);
    foreach ($users as $user) {
        $editBlock->addUserRow($user);
        $certificates = $user->getCertificates();
        foreach ($certificates as $certificate) {
            $editBlock->addCertificateRow($certificate);
        }
    }
    $builder->addContentElement($editBlock);
    
    //Append terms of use popup
    if(!$controller->isAgreementSigned()){
        $termsOfUse = new TermsOfUseBox('sb-popup-message', $controller->addQuery($_SERVER['SCRIPT_NAME']), TermsOfUseCommand::COMMAND, TermsOfUseCommand::AGREEMENT);
        $builder->addContentElement($termsOfUse);
    }
    
}

$builder->createPagePrelude();
?>

<?php echo $page->fetchMeta(); ?>

<?php echo $page->fetchCss(); ?>

<?php echo $page->fetchScript(); ?>

</head>
<body>
    
    <?php $builder->renderPageHeader(); ?>
    
    <?php $builder->renderPageContent(); ?>
    
    <?php $builder->renderPageFooter();

