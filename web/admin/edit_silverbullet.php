<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

/*
 * Class autoloader invocation, should be included prior to any other code at the entry points to the application
 */
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

use web\lib\admin\http\SilverbulletContext;
use web\lib\admin\http\SilverbulletController;
use web\lib\admin\http\TermsOfUseCommand;
use web\lib\admin\view\AddNewUserForm;
use web\lib\admin\view\ComposeEmailBox;
use web\lib\admin\view\DefaultHtmlPage;
use web\lib\admin\view\FileUploadForm;
use web\lib\admin\view\html\UnaryTag;
use web\lib\admin\view\InfoBlockTable;
use web\lib\admin\view\InstitutionPageBuilder;
use web\lib\admin\view\PageElementAdapter;
use web\lib\admin\view\PageElementInterface;
use web\lib\admin\view\PopupMessageContainer;
use web\lib\admin\view\TabbedPanelsBox;
use web\lib\admin\view\TermsOfUseBox;
use web\lib\admin\view\UserCredentialsForm;
use web\lib\admin\view\SendSmsBox;

$auth = new \web\lib\admin\Authentication();
$auth->authenticate();

$uiElements = new \web\lib\admin\UIElements();

$page = new DefaultHtmlPage(DefaultHtmlPage::ADMIN_IDP_USERS, sprintf(_('Managing %s users'),$uiElements->nomenclature_inst), '1.3.3');
// Load global scripts
$page->appendScript('js/option_expand.js');
$page->appendScript('../external/jquery/jquery.js');
$page->appendScript('../external/jquery/jquery-ui.js');
$page->appendScript('../external/jquery/jquery-migrate-1.2.1.js');
// Load Silverbullet scripts
$page->appendScript('js/silverbullet.js');
$page->appendScript('js/edit_silverbullet.js');
// Load global CSS
$page->appendCss('../external/jquery/jquery-ui.css');
// Load Silverbullet CSS
$page->appendCss('css/silverbullet.css');

$builder = new InstitutionPageBuilder($page);
$builder->buildPagePrelude();
$builder->buildPageHeader();
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
            throw new Exception("We were told to create a new SB profile, but this ".CONFIG_CONFASSISTANT['CONSORTIUM']['nomenclature_federation']." does not allow SB at all!");
        }
        // okay, new SB profiles are allowed. Create one.
        $newProfile = $inst->newProfile("SILVERBULLET");
        // and modify the REQUEST_URI to add the new profile ID
        $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI']."&profile_id=".$newProfile->identifier;
        $_GET['profile_id'] = $newProfile->identifier;
    }
    
    $context = new SilverbulletContext($builder);
    $controller = new SilverbulletController($context);
    $controller->parseRequest();
    
    $users = $context->createUsers();
    $stats = $context->getUserStats();
    $action = $context->addQuery($_SERVER['SCRIPT_NAME']);
    
    //Info block data preparation
    $infoBlock = new InfoBlockTable( sprintf(_('Current %s users'), $uiElements->nomenclature_inst));
    $infoBlock->addRow(array('The assigned realm', $builder->getRealmName()));
    $infoBlock->addRow(array('The total number of active users which are allowed for this profile', $stats[SilverbulletContext::STATS_TOTAL]));
    $infoBlock->addRow(array('The current number of configured active users', $stats[SilverbulletContext::STATS_ACTIVE]));
    $infoBlock->addRow(array('The current number of configured inactive users', $stats[SilverbulletContext::STATS_PASSIVE]));
    $builder->addContentElement($infoBlock);

    //Edit form data preparation
    $acknowledgeText = _ ( 'You need to acknowledge that the created accounts are still valid within the next %s days.'
                .' If all accounts shown as active above are indeed still valid, please check the box below and push "Save".'
                .' If any of the accounts are stale, please deactivate them by pushing the corresponding button before doing this.' );
    $editBlock = new UserCredentialsForm($context, $action, sprintf(_('Manage %s users'), $uiElements->nomenclature_inst), $acknowledgeText, count($users) > 0);
    foreach ($users as $user) {
        $editBlock->addUserRow($user);
        $certificates = $user->getCertificates();
        foreach ($certificates as $certificate) {
            $editBlock->addCertificateRow($certificate);
        }
        $invitations = $user->getInvitations();
        foreach ($invitations as $invitation) {
            $editBlock->addInvitationRow($invitation);
        }
        
        
    }
    $builder->addContentElement($editBlock);
    
    //Add new user and user import forms preparation
    $newUserFrom = new AddNewUserForm($context, $action, _("Please enter a username of your choice and user expiry date to create a new user:"));
    $importForm = new FileUploadForm($context, $action, _('Comma separated values should be provided in CSV file: username, expiration date "yyyy-mm-dd", number of tokens (optional):'));
    //Creating tabbed box and adding forms
    $tabbedBox = new TabbedPanelsBox();
    $tabbedBox->addTabbedPanel(_('Add new user'), $newUserFrom);
    $tabbedBox->addTabbedPanel(_('Import users from CSV file'), $importForm);
    $builder->addContentElement($tabbedBox);
    
    //Appending terms of use popup
    if(!$context->isAgreementSigned()){
        $termsOfUse = new TermsOfUseBox($action, TermsOfUseCommand::COMMAND, TermsOfUseCommand::AGREEMENT);
        $termsOfUsePopup = new PopupMessageContainer($termsOfUse, PageElementInterface::MESSAGEPOPUP_CLASS, \core\ProfileSilverbullet::PRODUCTNAME . " - " . _('Terms of Use'));
        $termsOfUsePopup->setCloseButtonClass('redirect');
        $builder->addContentElement($termsOfUsePopup);
    }
    
    //Adding hidden compose email popup template
    $composeEmail = new ComposeEmailBox($action, _('Choose how you want to send the message.'));
    $builder->addContentElement(new PopupMessageContainer($composeEmail, PageElementInterface::COMPOSE_EMAIL_CLASS, _('Compose Email'), false));

    //Adding hidden send in SMS popup template
    $sendSms = new SendSmsBox($action, _('Send invitation token in SMS message.'));
    $builder->addContentElement(new PopupMessageContainer($sendSms, PageElementInterface::SEND_SMS_CLASS, _('Send in SMS'), false));
    
    //Adding hidden QR code popup template
    $qrCodeImage = new UnaryTag("img");
    $qrCodeImage->addAttribute("id", PageElementInterface::INVITATION_QR_CODE_CLASS."-image");
    $qrCodeImage->addAttribute("alt", _('Invitation QR Code'));
    $qrCodeImage->addAttribute("width", 400);
    $qrCodeImage->addAttribute("height", 400);
    $invitationQrCode = new PopupMessageContainer(new PageElementAdapter($qrCodeImage), PageElementInterface::INVITATION_QR_CODE_CLASS, _('Invitation QR Code'), false);
    $builder->addContentElement($invitationQrCode);
    
}
$builder->buildPageFooter();

$page->fetchPrelude()->render();

$page->fetchMeta()->render();
$page->fetchCss()->render();
$page->fetchScript()->render();

?>
</head>
<body>
<?php

$page->render();
