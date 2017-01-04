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

require_once("Federation.php");
require_once("IdP.php");
require_once("Helper.php");
require_once("CAT.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/option_html.inc.php");
require_once("inc/geo_widget.php");
require_once("inc/auth.inc.php");

use lib\domain\SilverbulletFactory;
use lib\http\TermsOfUseValidator;
use lib\view\DefaultPage;
use lib\view\FileUploadForm;
use lib\view\InfoBlockTable;
use lib\view\InstitutionPageBuilder;
use lib\view\PageBuilder;
use lib\view\PageElementInterface;
use lib\view\TermosOfUseBox;
use lib\view\TitledBlockDecorator;
use lib\view\UserCredentialsForm;

authenticate();

$page = new DefaultPage(_('Managing institution users'));
$page->appendScript('js/silverbullet.js');
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
        $fed = new Federation($inst->federation);
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
    
    $profile = $builder->getProfile();
    $factory = new SilverbulletFactory($profile);
    $factory->parseRequest();
    
    $users = $factory->createUsers();
    $stats = $factory->getUserStats();
    
    //Info block data preparation
    $infoBlock = new InfoBlockTable( _('Current institution users'));
    $infoBlock->addRow(array('The assigned realm', $builder->getRealmName()));
    $infoBlock->addRow(array('The total number of active users which are allowed for this profile', $stats[SilverbulletFactory::STATS_TOTAL]));
    $infoBlock->addRow(array('The current number of configured active users', $stats[SilverbulletFactory::STATS_ACTIVE]));
    $infoBlock->addRow(array('The current number of configured inactive users', $stats[SilverbulletFactory::STATS_PASSIVE]));
    $builder->addContentElement($infoBlock);

    //User import form preparation
    $importForm = new FileUploadForm($factory, _('Comma separated values in should be provided in CSV file: username, expiration date "yyyy-mm-dd", number of invitations (optional):'));
    $importBlock = new TitledBlockDecorator($importForm, _('Import users from CSV file'), PageElementInterface::INFOBLOCK_CLASS);
    $builder->addContentElement($importBlock);

    //Edit form data preparation
    $editBlock = new UserCredentialsForm(_('Manage institution users'), $factory, count($users) > 0);
    foreach ($users as $user) {
        $editBlock->addUserRow($user);
        $certificates = $user->getCertificates();
        foreach ($certificates as $certificate) {
            $editBlock->addCertificateRow($certificate);
        }
    }
    $builder->addContentElement($editBlock);
    
    //Append terms of use popup
    $agreement_attributes = $profile->getAttributes("hiddenprofile:tou_accepted");
    if(count($agreement_attributes) == 0){
        $termsOfUse = new TermosOfUseBox('sb-terms-of-use', $factory->addQuery($_SERVER['SCRIPT_NAME']), TermsOfUseValidator::COMMAND, TermsOfUseValidator::AGREEMENT);
        $builder->addContentElement($termsOfUse);
    }
    
}

$cat = $builder->createPagePrelude();
?>

<?php echo $page->fetchMeta(); ?>

<?php echo $page->fetchCss(); ?>

<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-migrate-1.2.1.js"></script>
<script type="text/javascript" src="js/silverbullet.js"></script>
<script type="text/javascript" src="js/edit_silverbullet.js"></script>

<?php echo $page->fetchScript(); ?>

</head>
<body>
    
    <?php $builder->renderPageHeader(); ?>
    
    <?php $builder->renderPageContent(); ?>
    
    <?php $builder->renderPageFooter();

