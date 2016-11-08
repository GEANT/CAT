<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
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
use lib\view\DefaultPage;
use lib\view\InfoBlockTable;
use lib\view\InstitutionPageBuilder;
use lib\view\PageBuilder;
use lib\view\UserCredentialsForm;

authenticate();

$page = new DefaultPage(_('Managing institution users'));
$page->appendScript('js/silverbullet.js');
$page->appendCss('css/silverbullet.css');
$builder = new InstitutionPageBuilder($page, PageBuilder::ADMIN_IDP_USERS);
if($builder->isReady()){
    
    $factory = new SilverbulletFactory($builder->getProfile());
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
    
    //Edit form data preparation
    $editBlock = new UserCredentialsForm(_('Manage institution users'));
    $editBlock->addTitleRow(array('user' => 'User/CN', 'token' => 'One Time Token/Serial Number', 'expiry' => 'Token Expiry/Certificate Expiry', 'action' => 'Actions'));
    foreach ($users as $user) {
        $editBlock->addUserRow($user);
        $certificates = $user->getCertificates();
        $count = 1;
        foreach ($certificates as $certificate) {
            $editBlock->addCertificateRow($certificate, $count);
            $count++;
        }
    }
    
    $builder->addContentElement($editBlock);
}


$cat = $builder->createPagePrelude();
?>

<?php echo $page->fetchMeta(); ?>

<?php echo $page->fetchCss(); ?>

<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-migrate-1.2.1.js"></script>
<?php echo $page->fetchScript(); ?>

</head>
<body>
    
    <?php $builder->renderPageHeader(); ?>
    
    <?php $builder->renderPageContent(); ?>
    
    <?php $builder->renderPageFooter();

